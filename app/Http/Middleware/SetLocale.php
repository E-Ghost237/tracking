<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the locale from the route group (/ for English, /fr for French).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale = 'en'): Response
    {
        $locale = in_array($locale, config('platform.locales'), true) ? $locale : 'en';
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        if ($request->user() !== null && $request->user()->locale !== $locale && $request->isMethod('GET')) {
            $request->user()->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $next($request);
    }
}
