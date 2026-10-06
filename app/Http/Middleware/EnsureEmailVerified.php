<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paying and booking need a verified email address (section 2.1).
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => ['code' => 'email_not_verified', 'message' => __('Please verify your email address first.')]], 403);
            }

            return redirect()->route(app()->getLocale().'.account.verification.notice');
        }

        return $next($request);
    }
}
