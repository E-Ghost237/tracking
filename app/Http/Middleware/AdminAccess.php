<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office gate (FR-144, FR-149): optional IP allowlist, active staff account,
 * and two-factor authentication set up before any admin screen is shown.
 */
class AdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = config('platform.admin.ip_allowlist', []);
        if ($allowlist !== [] && ! in_array($request->ip(), $allowlist, true)) {
            abort(404);
        }

        $user = $request->user();
        if ($user !== null && $user->isStaff() && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route($user->preferredLocale().'.account.profile', ['setup2fa' => 1])
                ->with('status', __('Staff accounts must enable two-factor authentication before using the back-office.'));
        }

        return $next($request);
    }
}
