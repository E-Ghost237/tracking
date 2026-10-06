<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support for mutating customer endpoints (section 9.6): a double click
 * replays the first response instead of creating two orders or two proofs.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '' || $request->isMethodSafe()) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key)) {
            return response()->json(['error' => ['code' => 'invalid_idempotency_key', 'message' => 'Invalid Idempotency-Key header.']], 400);
        }

        $scope = 'idem:'.($request->user()?->getAuthIdentifier() ?? $request->ip()).':'.sha1($request->method().$request->path()).':'.$key;
        $lock = Cache::lock($scope.':lock', 30);

        if (! $lock->get()) {
            return response()->json(['error' => ['code' => 'request_in_progress', 'message' => 'The same request is already being processed.']], 409);
        }

        try {
            $stored = Cache::get($scope);
            if (is_array($stored)) {
                return (new JsonResponse($stored['body'], $stored['status']))->header('Idempotent-Replayed', 'true');
            }

            $response = $next($request);

            if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
                Cache::put($scope, ['status' => $response->getStatusCode(), 'body' => $response->getData(true)], now()->addDay());
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
