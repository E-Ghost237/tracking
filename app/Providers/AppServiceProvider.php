<?php

namespace App\Providers;

use App\Contracts\GeocodingProvider;
use App\Contracts\MalwareScanner;
use App\Contracts\NotificationChannel;
use App\Contracts\PaymentGateway;
use App\Contracts\TrackingProvider;
use App\Services\Files\ClamAvScanner;
use App\Services\Files\HeuristicScanner;
use App\Services\Geocoding\CachedGeocoder;
use App\Services\Geocoding\LocalCityGeocoder;
use App\Services\Geocoding\MapboxGeocoder;
use App\Services\Geocoding\OpenCageGeocoder;
use App\Services\Notifications\MailChannel;
use App\Services\Payments\ManualProofGateway;
use App\Services\Settings;
use App\Services\Tracking\AfterShipTrackingProvider;
use App\Services\Tracking\NullTrackingProvider;
use App\Support\FieldEncrypter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(FieldEncrypter::class, fn () => new FieldEncrypter(config('platform.security.field_encryption_key')));

        $this->app->bind(TrackingProvider::class, fn () => match (config('platform.tracking.provider')) {
            'aftership' => new AfterShipTrackingProvider(config('platform.tracking.aftership_key')),
            default => new NullTrackingProvider,
        });

        $this->app->bind(GeocodingProvider::class, function () {
            $inner = match (config('platform.geocoding.driver')) {
                'mapbox' => new MapboxGeocoder((string) config('platform.geocoding.mapbox_token')),
                'opencage' => new OpenCageGeocoder((string) config('platform.geocoding.opencage_key')),
                default => new LocalCityGeocoder,
            };

            return new CachedGeocoder($inner, (int) config('platform.geocoding.cache_days', 30));
        });

        $this->app->bind(MalwareScanner::class, fn () => config('platform.security.malware_scanner') === 'clamav'
            ? new ClamAvScanner(config('platform.security.clamav_host'), (int) config('platform.security.clamav_port'))
            : new HeuristicScanner);

        $this->app->bind(PaymentGateway::class, ManualProofGateway::class);
        $this->app->bind(NotificationChannel::class, MailChannel::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers()->max(128);

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });

        $this->configureRateLimiting();
    }

    /**
     * Rate limits from the API specification (section 9) and FR-147.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('global', fn (Request $request) => Limit::perMinute((int) config('platform.security.global_rate_per_minute', 240))->by('g:'.$request->ip()));

        RateLimiter::for('track', fn (Request $request) => Limit::perMinute(30)->by('track:'.$request->ip()));
        RateLimiter::for('quotes', fn (Request $request) => Limit::perMinute(20)->by('quote:'.$request->ip()));
        RateLimiter::for('geocode', fn (Request $request) => Limit::perMinute(60)->by('geo:'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by('contact:'.$request->ip()));
        RateLimiter::for('subscriptions', fn (Request $request) => Limit::perHour(5)->by('sub:'.$request->ip()));
        RateLimiter::for('captcha', fn (Request $request) => Limit::perMinute(30)->by('captcha:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(6)->by('2fa:'.$request->session()->getId().'|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perHour(10)->by('reset-ip:'.$request->ip()),
            Limit::perHour(3)->by('reset-email:'.Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('payment-method', fn (Request $request) => Limit::perMinute(10)->by('pm:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('proofs', fn (Request $request) => Limit::perHour(15)->by('proof:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('customer', fn (Request $request) => Limit::perMinute(120)->by('cust:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by('upl:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by('wh:'.$request->ip()));
    }
}
