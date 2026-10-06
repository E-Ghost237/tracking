<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API, base path /api/v1 (section 9)
|--------------------------------------------------------------------------
| The website calls these with its session cookie and CSRF token (Sanctum
| stateful requests). Requests from other origins get no session.
*/

// 9.1 Public endpoints
Route::get('/track', Api\TrackController::class)->middleware('throttle:track')->name('api.track');
Route::get('/track/{number}/qr', [Api\TrackController::class, 'qr'])->where('number', '[A-Za-z0-9\-]{4,40}')->middleware('throttle:track')->name('api.track.qr');
Route::post('/quotes', [Api\QuoteController::class, 'store'])->middleware('throttle:quotes')->name('api.quotes.store');
Route::get('/geocode', [Api\GeocodeController::class, 'search'])->middleware('throttle:geocode')->name('api.geocode');
Route::get('/geocode/reverse', [Api\GeocodeController::class, 'reverse'])->middleware('throttle:geocode')->name('api.geocode.reverse');
Route::get('/locations', Api\LocationController::class)->name('api.locations');
Route::get('/network', Api\NetworkController::class)->name('api.network');
Route::get('/content/{slug}', Api\ContentController::class)->where('slug', '[a-z0-9\-]{1,60}')->name('api.content');
Route::post('/contact', Api\ContactController::class)->middleware('throttle:contact')->name('api.contact');
Route::post('/tracking-subscriptions', Api\TrackingSubscriptionController::class)->middleware('throttle:subscriptions')->name('api.subscriptions');
Route::get('/captcha', Api\CaptchaController::class)->middleware('throttle:captcha')->name('api.captcha');
Route::post('/webhooks/tracking/{provider}', Api\TrackingWebhookController::class)->where('provider', '[a-z0-9]{2,20}')->middleware('throttle:webhooks')->name('api.webhooks.tracking');

// 9.3 Customer endpoints (login required, owner only)
Route::middleware(['auth:sanctum', 'throttle:customer', 'no-store'])->group(function (): void {
    Route::get('/me', [Api\AccountController::class, 'me'])->name('api.me');

    Route::get('/addresses', [Api\AddressController::class, 'index'])->name('api.addresses.index');
    Route::post('/addresses', [Api\AddressController::class, 'store'])->middleware('idempotent')->name('api.addresses.store');
    Route::put('/addresses/{address}', [Api\AddressController::class, 'update'])->name('api.addresses.update');
    Route::delete('/addresses/{address}', [Api\AddressController::class, 'destroy'])->name('api.addresses.destroy');

    Route::get('/quotes', [Api\QuoteController::class, 'index'])->name('api.quotes.index');

    Route::middleware('verified')->group(function (): void {
        Route::post('/quotes/{quote}/book', [Api\QuoteController::class, 'book'])->middleware('idempotent')->name('api.quotes.book');

        Route::post('/shipment-drafts', [Api\ShipmentDraftController::class, 'store'])->middleware('idempotent')->name('api.drafts.store');
        Route::get('/shipment-drafts/{draft}', [Api\ShipmentDraftController::class, 'show'])->name('api.drafts.show');
        Route::put('/shipment-drafts/{draft}', [Api\ShipmentDraftController::class, 'update'])->name('api.drafts.update');
        Route::post('/shipment-drafts/{draft}/price', [Api\ShipmentDraftController::class, 'price'])->middleware('throttle:quotes')->name('api.drafts.price');
        Route::post('/shipments', [Api\ShipmentController::class, 'store'])->middleware('idempotent')->name('api.shipments.store');

        Route::get('/orders/{order}/payment-methods', [Api\PaymentController::class, 'methods'])->name('api.orders.methods');
        Route::post('/orders/{order}/payment-method', [Api\PaymentController::class, 'select'])->middleware('throttle:payment-method')->name('api.orders.select');
        Route::get('/orders/{order}/payment-method', [Api\PaymentController::class, 'current'])->middleware('throttle:payment-method')->name('api.orders.current');
        Route::post('/orders/{order}/proofs', [Api\PaymentController::class, 'upload'])->middleware(['throttle:proofs', 'idempotent'])->name('api.orders.proofs.store');
    });

    Route::get('/shipments', [Api\ShipmentController::class, 'index'])->name('api.shipments.index');
    Route::get('/shipments/{shipment}', [Api\ShipmentController::class, 'show'])->name('api.shipments.show');

    Route::get('/orders', [Api\OrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{order}', [Api\OrderController::class, 'show'])->name('api.orders.show');
    Route::get('/orders/{order}/invoice', [Api\OrderController::class, 'invoice'])->name('api.orders.invoice');
    Route::get('/orders/{order}/proofs', [Api\PaymentController::class, 'proofs'])->name('api.orders.proofs.index');

    Route::get('/tickets', [Api\TicketController::class, 'index'])->name('api.tickets.index');
    Route::post('/tickets', [Api\TicketController::class, 'store'])->middleware(['throttle:10,60', 'idempotent'])->name('api.tickets.store');
    Route::post('/tickets/{ticket}/messages', [Api\TicketController::class, 'reply'])->middleware('throttle:20,60')->name('api.tickets.reply');

    Route::post('/claims', [Api\ClaimController::class, 'store'])->middleware(['throttle:5,60', 'idempotent'])->name('api.claims.store');
});
