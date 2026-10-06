<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\TrackingSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Adds tracking events with a recorded source and author (R6), keeps the shipment status
 * equal to the latest public event (BR-10) and protects the Delivered event (BR-08).
 */
class ShipmentEventService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{status: string, label: string, place?: ?string, lat?: ?float, lon?: ?float, occurred_at: CarbonInterface|string, is_public?: bool, note?: ?string, provider_event_id?: ?string}  $data
     */
    public function add(Shipment $shipment, array $data, ?User $author, string $source = 'admin'): ShipmentEvent
    {
        $status = ShipmentStatus::from($data['status']);

        [$event, $statusChanged] = DB::transaction(function () use ($shipment, $data, $author, $source, $status): array {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (! $shipment->isReleased()) {
                throw new DomainRuleException('not_released', __('Events can only be added once payment is approved and the shipment is released.'), 409);
            }

            $hasDelivered = $shipment->events()->where('status', ShipmentStatus::Delivered->value)->exists();
            if ($hasDelivered && $status === ShipmentStatus::Delivered) {
                throw new DomainRuleException('already_delivered', __('This shipment already has a Delivered event.'), 409);
            }
            if ($hasDelivered && $source !== 'api' && ! $author?->hasPermission(Permissions::EVENTS_AFTER_DELIVERY)) {
                throw new DomainRuleException('after_delivery', __('Only an admin can add events after delivery.'), 403);
            }

            if (! empty($data['provider_event_id']) && $shipment->events()->where('provider_event_id', $data['provider_event_id'])->exists()) {
                return [$shipment->events()->where('provider_event_id', $data['provider_event_id'])->first(), false];
            }

            $event = new ShipmentEvent;
            $event->forceFill([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'label' => mb_substr(trim($data['label']), 0, 250),
                'place' => isset($data['place']) ? mb_substr(trim((string) $data['place']), 0, 250) : null,
                'lat' => $data['lat'] ?? null,
                'lon' => $data['lon'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'source' => $source,
                'is_public' => (bool) ($data['is_public'] ?? true),
                'note' => $data['note'] ?? null,
                'provider_event_id' => $data['provider_event_id'] ?? null,
                'created_by' => $author?->id,
            ])->save();

            $statusChanged = false;
            $latest = $shipment->events()->where('is_public', true)->first();

            if ($latest !== null && $latest->is($event)) {
                $statusChanged = $shipment->status !== $status;
                $shipment->status = $status;
                $shipment->progress = max($shipment->progress, $status->defaultProgress());
                if ($event->lat !== null && $event->lon !== null) {
                    $shipment->current_lat = $event->lat;
                    $shipment->current_lon = $event->lon;
                    $shipment->current_place = $event->place;
                }
                if ($status === ShipmentStatus::PickedUp && $shipment->picked_up_at === null) {
                    $shipment->picked_up_at = $event->occurred_at;
                }
                if ($status === ShipmentStatus::Delivered) {
                    $shipment->delivered_at = $event->occurred_at;
                    $shipment->progress = 1;
                    if (isset($shipment->destination['lat'])) {
                        $shipment->current_lat = $shipment->destination['lat'];
                        $shipment->current_lon = $shipment->destination['lon'];
                    }
                }
                $shipment->save();
            }

            $this->audit->log('shipment.event_added', $shipment, null, [
                'status' => $status->value,
                'label' => $event->label,
                'place' => $event->place,
                'occurred_at' => (string) $event->occurred_at,
                'source' => $source,
                'is_public' => $event->is_public,
            ], $author);

            return [$event, $statusChanged];
        });

        if ($statusChanged && $event->is_public && $status->notifiesSubscribers()) {
            $this->notifyStatusChange($shipment->fresh(['user']), $event);
        }

        return $event;
    }

    private function notifyStatusChange(Shipment $shipment, ShipmentEvent $event): void
    {
        if ($shipment->user !== null) {
            $locale = $shipment->user->preferredLocale();
            $this->notifications->send('shipment.status_changed', $shipment->user, [
                'tracking_number' => (string) $shipment->tracking_number,
                'status' => $event->status->label(),
                'place' => (string) $event->place,
                'tracking_url' => route($locale.'.track', ['number' => $shipment->tracking_number]),
            ]);
        }

        TrackingSubscription::query()
            ->where('tracking_number', $shipment->tracking_number)
            ->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at')
            ->each(function (TrackingSubscription $subscription) use ($shipment, $event): void {
                $this->notifications->send('shipment.status_changed', $subscription->email, [
                    'tracking_number' => (string) $shipment->tracking_number,
                    'status' => __($event->status->label(), [], $subscription->locale),
                    'place' => (string) $event->place,
                    'tracking_url' => route($subscription->locale.'.track', ['number' => $shipment->tracking_number]),
                    'unsubscribe_url' => URL::signedRoute('tracking.unsubscribe', ['subscription' => $subscription->id]),
                ], $subscription->locale);
            });
    }
}
