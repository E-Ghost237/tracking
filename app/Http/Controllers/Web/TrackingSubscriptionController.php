<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TrackingSubscription;
use App\Services\Shipping\TrackingSubscriptionService;
use Illuminate\Http\RedirectResponse;

class TrackingSubscriptionController extends Controller
{
    public function confirm(string $token, TrackingSubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = strlen($token) === 48 ? $subscriptions->confirm($token) : null;
        $locale = $subscription->locale ?? 'en';

        return redirect()->route($locale.'.track', $subscription ? ['number' => $subscription->tracking_number] : [])
            ->with('status', $subscription ? __('Tracking alerts are now active for this shipment.', [], $locale) : __('This confirmation link is invalid or has expired.', [], $locale));
    }

    public function unsubscribe(TrackingSubscription $subscription): RedirectResponse
    {
        $subscription->forceFill(['unsubscribed_at' => now()])->save();

        return redirect()->route($subscription->locale.'.home')->with('status', __('You will no longer receive alerts for this shipment.', [], $subscription->locale));
    }
}
