<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CaptchaService;
use App\Services\Tracking\CarrierDetector;
use App\Services\Tracking\TrackingService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/track?numbers=a,b (FR-10 to FR-18). Public fields only; CAPTCHA after
 * repeated failed lookups from one address.
 */
class TrackController extends Controller
{
    public function __invoke(Request $request, CarrierDetector $detector, TrackingService $tracking, CaptchaService $captcha): JsonResponse
    {
        $request->validate(['numbers' => ['required', 'string', 'max:2000']]);

        $numbers = $detector->parse((string) $request->query('numbers'), (int) config('platform.tracking.max_numbers', 20));
        if ($numbers === []) {
            return response()->json(['error' => ['code' => 'validation_failed', 'message' => __('Enter at least one tracking number.')]], 422);
        }

        $failureKey = 'track-fail:'.$request->ip();
        $failures = (int) Cache::get($failureKey, 0);

        if ($failures >= (int) config('platform.tracking.captcha_after_failures', 10)) {
            $solved = $request->hasSession() && $captcha->verify($request, $request->header('X-Captcha-Id'), $request->header('X-Captcha-Answer'));
            if (! $solved) {
                return response()->json(['error' => [
                    'code' => 'captcha_required',
                    'message' => __('Please confirm you are not a robot to continue tracking.'),
                ]], 429);
            }
            Cache::forget($failureKey);
            $failures = 0;
        }

        $results = $tracking->lookup($numbers);
        $notFound = count(array_filter($results, fn (array $r) => ! $r['found']));
        if ($notFound > 0) {
            Cache::put($failureKey, $failures + $notFound, now()->addHour());
        }

        return response()->json(['results' => $results])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * QR code for the shareable tracking link (FR-16). Encodes only the public URL.
     */
    public function qr(Request $request, string $number, CarrierDetector $detector): Response
    {
        $canonical = $detector->canonical($number);
        $locale = in_array($request->query('locale'), config('platform.locales'), true) ? $request->query('locale') : 'en';
        $url = route($locale.'.track', ['number' => $canonical]);

        $svg = Cache::remember('qr:'.sha1($url), now()->addDay(), fn () => (new QRCode(new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ])))->render($url));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
