<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-controlled maintenance mode (FR-129). Staff can still sign in and use the back-office.
 */
class MaintenanceMode
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('maintenance_mode', false) || $request->user()?->isStaff()) {
            return $next($request);
        }

        $adminPath = trim((string) config('platform.admin.path'), '/');
        if ($request->is($adminPath, $adminPath.'/*', 'livewire*', '*/login', 'login', 'two-factor', '*/two-factor', 'up')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'maintenance', 'message' => __('We are performing scheduled maintenance. Please try again shortly.')]], 503);
        }

        return response()->view('errors.503', [], 503);
    }
}
