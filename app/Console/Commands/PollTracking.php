<?php

namespace App\Console\Commands;

use App\Contracts\TrackingProvider;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentEventService;
use Illuminate\Console\Command;

class PollTracking extends Command
{
    protected $signature = 'tracking:poll';

    protected $description = 'Poll the aggregator for active shipments with a partner number (section 14.5).';

    public function handle(TrackingProvider $provider, ShipmentEventService $events): int
    {
        if (! $provider->isConfigured()) {
            $this->info('No tracking provider configured.');

            return self::SUCCESS;
        }

        Shipment::query()
            ->with('partnerCarrier')
            ->whereNotNull('released_at')
            ->whereNotNull('partner_tracking_number')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->orderBy('id')
            ->each(function (Shipment $shipment) use ($provider, $events): void {
                $data = $provider->track($shipment->partnerCarrier, $shipment->partner_tracking_number);
                foreach (array_reverse($data['events'] ?? []) as $event) {
                    if ($event['at'] === '') {
                        continue;
                    }
                    rescue(fn () => $events->add($shipment, [
                        'status' => $event['status'],
                        'label' => $event['label'] ?: ucfirst(str_replace('_', ' ', $event['status'])),
                        'place' => $event['place'],
                        'occurred_at' => $event['at'],
                        'is_public' => true,
                        'provider_event_id' => sha1($shipment->partner_tracking_number.$event['at'].$event['label']),
                    ], null, 'api'), report: false);
                }
            });

        return self::SUCCESS;
    }
}
