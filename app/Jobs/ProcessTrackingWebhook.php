<?php

namespace App\Jobs;

use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\TrackingCache;
use App\Models\WebhookEvent;
use App\Services\Shipping\ShipmentEventService;
use App\Services\Tracking\AfterShipTrackingProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a stored aggregator webhook: refreshes the tracking cache and appends partner
 * carrier scans to linked shipments. Idempotent on provider event ids (section 9.6).
 */
class ProcessTrackingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $webhookEventId) {}

    public function handle(AfterShipTrackingProvider $normaliser, ShipmentEventService $events): void
    {
        $webhook = WebhookEvent::query()->find($this->webhookEventId);
        if ($webhook === null || $webhook->processed_at !== null) {
            return;
        }

        try {
            $tracking = (array) data_get($webhook->payload, 'msg', []);
            $number = strtoupper((string) ($tracking['tracking_number'] ?? ''));
            $carrier = Carrier::query()->where('code', (string) ($tracking['slug'] ?? ''))->first();
            $normalised = $normaliser->normalise($tracking);

            if ($carrier !== null && $number !== '' && $normalised !== null) {
                TrackingCache::query()->updateOrCreate(['carrier_id' => $carrier->id, 'number' => $number], ['payload' => $normalised, 'fetched_at' => now()]);

                $shipment = Shipment::query()->where('partner_carrier_id', $carrier->id)->where('partner_tracking_number', $number)->whereNotNull('released_at')->first();
                if ($shipment !== null) {
                    foreach (array_reverse($normalised['events']) as $event) {
                        if ($event['at'] === '') {
                            continue;
                        }
                        $events->add($shipment, [
                            'status' => $event['status'],
                            'label' => $event['label'] ?: ucfirst(str_replace('_', ' ', $event['status'])),
                            'place' => $event['place'],
                            'occurred_at' => $event['at'],
                            'is_public' => true,
                            'provider_event_id' => sha1($number.$event['at'].$event['label']),
                        ], null, 'api');
                    }
                }
            }

            $webhook->forceFill(['processed_at' => now(), 'error' => null])->save();
        } catch (Throwable $e) {
            $webhook->forceFill(['error' => mb_substr($e->getMessage(), 0, 250)])->save();
            Log::warning('Tracking webhook failed', ['id' => $webhook->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
