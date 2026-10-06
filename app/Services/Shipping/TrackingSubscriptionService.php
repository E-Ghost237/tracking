<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Models\TrackingSubscription;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Str;

/**
 * Email alerts for any tracking number with double opt-in (FR-17).
 */
class TrackingSubscriptionService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Always behaves the same way, whether or not the address is already subscribed,
     * so the endpoint cannot be used to learn who follows a parcel.
     */
    public function subscribe(string $number, string $email, string $locale): void
    {
        $email = Str::lower(trim($email));
        $token = Str::random(48);

        $subscription = TrackingSubscription::query()->firstOrNew(['tracking_number' => $number, 'email' => $email]);
        if ($subscription->exists && $subscription->verified_at !== null && $subscription->unsubscribed_at === null) {
            return;
        }

        $subscription->fill([
            'shipment_id' => Shipment::query()->where('tracking_number', $number)->whereNotNull('released_at')->value('id'),
            'locale' => $locale,
            'token_hash' => hash('sha256', $token),
            'verified_at' => null,
            'unsubscribed_at' => null,
        ])->save();

        $this->notifications->send('tracking.confirm_subscription', $email, [
            'tracking_number' => $number,
            'confirm_url' => route('tracking.confirm', ['token' => $token]),
        ], $locale);
    }

    public function confirm(string $token): ?TrackingSubscription
    {
        $subscription = TrackingSubscription::query()->where('token_hash', hash('sha256', $token))->first();
        if ($subscription === null || $subscription->updated_at->lt(now()->subDays(7))) {
            return null;
        }

        $subscription->forceFill(['verified_at' => now(), 'token_hash' => hash('sha256', Str::random(48))])->save();

        return $subscription;
    }
}
