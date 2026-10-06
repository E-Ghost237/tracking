<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->statefulApi();
        $middleware->authenticateSessions();

        $middleware->web(prepend: [SecurityHeaders::class], append: [
            EnsureAccountActive::class,
            MaintenanceMode::class,
            'throttle:global',
        ]);

        $middleware->api(prepend: [SecurityHeaders::class, ApiLocale::class], append: [
            EnsureAccountActive::class,
            MaintenanceMode::class,
            'throttle:global',
        ]);

        $middleware->alias([
            'locale' => SetLocale::class,
            'no-store' => NoStore::class,
            'idempotent' => EnsureIdempotency::class,
            'admin.access' => AdminAccess::class,
            'verified' => EnsureEmailVerified::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route(app()->getLocale().'.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route(app()->getLocale().'.account.dashboard'));

        $middleware->validateCsrfTokens(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'gift_card.code', 'gift_card.pin']);

        // One error shape for the whole API: { "error": { "code": "...", "message": "..." } } (section 9).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code, $message, $extra] = match (true) {
                $e instanceof ValidationException => [422, 'validation_failed', __('Some fields need your attention.'), ['fields' => $e->errors()]],
                $e instanceof AuthenticationException => [401, 'unauthenticated', __('Please sign in to continue.'), []],
                $e instanceof AuthorizationException => [403, 'forbidden', __('You are not allowed to do this.'), []],
                $e instanceof ThrottleRequestsException => [429, 'too_many_requests', __('Too many requests. Please wait a moment and try again.'), []],
                $e instanceof TokenMismatchException => [419, 'csrf_mismatch', __('Your session expired. Refresh the page and try again.'), []],
                $e instanceof NotFoundHttpException, $e instanceof RecordsNotFoundException => [404, 'not_found', __('Not found.'), []],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', __('The request could not be completed.'), []],
                default => [null, null, null, []],
            };

            if ($status === null) {
                return null;
            }

            $response = response()->json(['error' => array_merge(['code' => $code, 'message' => $message], $extra)], $status);

            if ($e instanceof ThrottleRequestsException) {
                foreach ($e->getHeaders() as $name => $value) {
                    $response->headers->set($name, (string) $value);
                }
            }

            return $response;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! config('app.debug') && ! $e instanceof HttpExceptionInterface && ! method_exists($e, 'render')) {
                return response()->json(['error' => ['code' => 'server_error', 'message' => __('Something went wrong on our side. Please try again.')]], 500);
            }

            return null;
        });
    })->create();
