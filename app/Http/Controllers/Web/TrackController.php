<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tracking\CarrierDetector;
use App\Services\Tracking\TrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Tracking page. A shareable /track/{number} link is rendered on the server (FR-16);
 * multi-number lookups run through the rate-limited API.
 */
class TrackController extends Controller
{
    public function __invoke(Request $request, CarrierDetector $detector, TrackingService $tracking, ?string $number = null): View
    {
        $result = null;
        $failureKey = 'track-fail:'.$request->ip();

        if ($number !== null) {
            $canonical = $detector->canonical($number);
            $limited = RateLimiter::tooManyAttempts('track-page:'.$request->ip(), 30)
                || (int) Cache::get($failureKey, 0) >= (int) config('platform.tracking.captcha_after_failures', 10);

            if (! $limited && $canonical !== '') {
                RateLimiter::hit('track-page:'.$request->ip(), 60);
                $result = $tracking->lookupOne($canonical);
                if (! $result['found']) {
                    Cache::put($failureKey, (int) Cache::get($failureKey, 0) + 1, now()->addHour());
                }
            }
            $number = $canonical;
        }

        return view('pages.track', ['number' => $number, 'result' => $result]);
    }
}
