<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks EN or FR for API responses from ?locale, X-Locale or Accept-Language.
 */
class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('platform.locales');
        $candidate = $request->query('locale') ?? $request->header('X-Locale') ?? $request->getPreferredLanguage($supported);
        app()->setLocale(in_array($candidate, $supported, true) ? $candidate : 'en');

        return $next($request);
    }
}
