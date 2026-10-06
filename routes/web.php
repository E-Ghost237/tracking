<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locale-independent routes
|--------------------------------------------------------------------------
*/
Route::get('/files/{file}', Web\FileController::class)->middleware(['auth', 'signed', 'no-store'])->name('files.show');
Route::get('/tracking/confirm/{token}', [Web\TrackingSubscriptionController::class, 'confirm'])->middleware('throttle:20,1')->name('tracking.confirm');
Route::get('/tracking/unsubscribe/{subscription}', [Web\TrackingSubscriptionController::class, 'unsubscribe'])->middleware('signed')->name('tracking.unsubscribe');
Route::get('/notifications/unsubscribe/{user}/{event}', Web\UnsubscribeController::class)->middleware('signed')->name('notifications.unsubscribe');
Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::get('/sitemap.xml', [Web\SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Web\SeoController::class, 'robots'])->name('robots');
Route::get('/preview/pages/{page}', Web\PagePreviewController::class)->middleware(['auth', 'no-store'])->name('pages.preview');

// Unprefixed name the framework falls back to for unauthenticated redirects.
Route::get('/sign-in', fn () => redirect()->route('en.login'))->name('login');

/*
|--------------------------------------------------------------------------
| Localised site: English at /, French at /fr (section 11.4)
|--------------------------------------------------------------------------
*/
foreach (config('platform.locales') as $locale) {
    $s = fn (string $key): string => trans('routes.'.$key, [], $locale);

    Route::prefix($locale === 'en' ? '' : $locale)
        ->name($locale.'.')
        ->middleware('locale:'.$locale)
        ->group(function () use ($s): void {
            // Public pages
            Route::get('/', Web\HomeController::class)->name('home');
            Route::get($s('track').'/{number?}', Web\TrackController::class)->where('number', '[A-Za-z0-9 \-]{1,60}')->name('track');
            Route::get($s('quote'), Web\QuoteController::class)->name('quote');
            Route::get($s('services'), [Web\ServicesController::class, 'index'])->name('services');
            Route::get($s('services').'/{mode}', [Web\ServicesController::class, 'show'])->where('mode', '[a-z]{3,10}')->name('services.show');
            Route::get($s('network'), Web\NetworkController::class)->name('network');
            Route::get($s('rates'), Web\RatesController::class)->name('rates');
            Route::get($s('locations'), Web\LocationsController::class)->name('locations');
            Route::get($s('help'), Web\HelpController::class)->name('help');
            Route::get($s('status'), Web\StatusController::class)->name('status');
            Route::get($s('contact'), [Web\ContactController::class, 'show'])->name('contact');
            Route::post($s('contact'), [Web\ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

            foreach (['customs', 'packing', 'about', 'terms', 'privacy', 'cookies', 'shipping-policy', 'claims-policy', 'prohibited-items', 'refund-policy', 'payment-terms'] as $slug) {
                Route::get($s($slug), Web\PageController::class)->defaults('slug', $slug)->name('page.'.$slug);
            }

            // Authentication
            Route::middleware('guest')->group(function () use ($s): void {
                Route::get($s('login'), [Auth\LoginController::class, 'show'])->name('login');
                Route::post($s('login'), [Auth\LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
                Route::get($s('two-factor'), [Auth\TwoFactorChallengeController::class, 'show'])->name('two-factor');
                Route::post($s('two-factor'), [Auth\TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.store');
                Route::get($s('register'), [Auth\RegisterController::class, 'show'])->name('register');
                Route::post($s('register'), [Auth\RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
                Route::get($s('forgot-password'), [Auth\PasswordResetController::class, 'request'])->name('password.request');
                Route::post($s('forgot-password'), [Auth\PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
                Route::get($s('reset-password').'/{token}', [Auth\PasswordResetController::class, 'edit'])->name('password.reset');
                Route::post($s('reset-password'), [Auth\PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
            });

            Route::post($s('logout'), [Auth\LoginController::class, 'destroy'])->middleware('auth')->name('logout');

            // Customer account (login required, owner only)
            Route::prefix($s('account'))->name('account.')->middleware(['auth', 'no-store'])->group(function () use ($s): void {
                Route::get($s('verify-email'), [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
                Route::post($s('verify-email'), [Auth\EmailVerificationController::class, 'send'])->middleware('throttle:3,10')->name('verification.send');

                Route::get('/', Account\DashboardController::class)->name('dashboard');
                Route::get($s('profile'), [Account\ProfileController::class, 'show'])->name('profile');
                Route::put($s('profile'), [Account\ProfileController::class, 'update'])->name('profile.update');
                Route::put($s('profile').'/password', [Account\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
                Route::post($s('profile').'/two-factor', [Account\TwoFactorController::class, 'start'])->middleware('throttle:10,1')->name('profile.2fa.start');
                Route::post($s('profile').'/two-factor/confirm', [Account\TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('profile.2fa.confirm');
                Route::delete($s('profile').'/two-factor', [Account\TwoFactorController::class, 'disable'])->middleware('throttle:6,1')->name('profile.2fa.disable');
                Route::post($s('profile').'/export', [Account\ProfileController::class, 'export'])->middleware('throttle:3,60')->name('profile.export');
                Route::post($s('profile').'/delete', [Account\ProfileController::class, 'requestDeletion'])->middleware('throttle:3,60')->name('profile.delete');

                Route::get($s('notifications'), [Account\NotificationController::class, 'show'])->name('notifications');
                Route::put($s('notifications'), [Account\NotificationController::class, 'update'])->name('notifications.update');
                Route::get($s('addresses'), Account\AddressController::class)->name('addresses');
                Route::get($s('quotes'), [Account\QuoteController::class, 'index'])->name('quotes');
                Route::post($s('quotes').'/{quote}/book', [Account\QuoteController::class, 'book'])->middleware('verified')->name('quotes.book');

                Route::get($s('shipments'), [Account\ShipmentController::class, 'index'])->name('shipments');
                Route::get($s('shipments').'/'.$s('new'), [Account\ShipmentController::class, 'create'])->middleware('verified')->name('shipments.create');
                Route::get($s('shipments').'/{shipment}', [Account\ShipmentController::class, 'show'])->name('shipments.show');
                Route::get($s('shipments').'/{shipment}/documents/{type}', Account\DocumentController::class)->where('type', '[a-z_]+')->name('shipments.document');

                Route::get($s('orders'), [Account\OrderController::class, 'index'])->name('orders');
                Route::get($s('orders').'/{order}/'.$s('pay'), [Account\OrderController::class, 'pay'])->middleware('verified')->name('orders.pay');
                Route::get($s('orders').'/{order}/invoices/{invoice}', [Account\OrderController::class, 'invoice'])->name('orders.invoice');
                Route::post($s('orders').'/{order}/cancel', [Account\OrderController::class, 'cancel'])->name('orders.cancel');

                Route::get($s('support'), [Account\SupportController::class, 'index'])->name('support');
                Route::post($s('support'), [Account\SupportController::class, 'store'])->middleware('throttle:10,60')->name('support.store');
                Route::get($s('support').'/{ticket}', [Account\SupportController::class, 'show'])->name('support.show');
                Route::post($s('support').'/{ticket}', [Account\SupportController::class, 'reply'])->middleware('throttle:20,60')->name('support.reply');

                Route::get($s('claims'), [Account\ClaimController::class, 'index'])->name('claims');
                Route::post($s('claims'), [Account\ClaimController::class, 'store'])->middleware('throttle:5,60')->name('claims.store');
            });
        });
}
