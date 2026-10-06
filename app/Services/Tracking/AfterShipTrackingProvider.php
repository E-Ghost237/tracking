<?php

namespace App\Services\Tracking;

use App\Contracts\TrackingProvider;
use App\Models\Carrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AfterShip aggregator adapter (one API for USPS, UPS and FedEx). Swappable behind TrackingProvider.
 */
class AfterShipTrackingProvider implements TrackingProvider
{
    private const BASE_URL = 'https://api.aftership.com/v4';

    private const SLUGS = ['usps' => 'usps', 'ups' => 'ups', 'fedex' => 'fedex'];

    public function __construct(private readonly ?string $apiKey) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function track(Carrier $carrier, string $number): ?array
    {
        $slug = self::SLUGS[$carrier->code] ?? null;
        if ($slug === null || ! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders(['aftership-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(3)
                ->retry(2, 250, throw: false)
                ->get(self::BASE_URL.'/trackings/'.$slug.'/'.rawurlencode($number));

            if ($response->status() === 404) {
                // Register the number so the aggregator starts polling; data arrives later by webhook.
                Http::withHeaders(['aftership-api-key' => $this->apiKey])->acceptJson()->timeout(8)
                    ->post(self::BASE_URL.'/trackings', ['tracking' => ['slug' => $slug, 'tracking_number' => $number]]);

                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            return $this->normalise((array) $response->json('data.tracking', []));
        } catch (Throwable $e) {
            Log::warning('Tracking provider error', ['carrier' => $carrier->code, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $tracking
     * @return array<string, mixed>|null
     */
    public function normalise(array $tracking): ?array
    {
        if ($tracking === []) {
            return null;
        }

        $events = [];
        foreach ((array) ($tracking['checkpoints'] ?? []) as $checkpoint) {
            $events[] = [
                'label' => (string) ($checkpoint['message'] ?? ''),
                'place' => trim(implode(', ', array_filter([$checkpoint['city'] ?? null, $checkpoint['country_iso3'] ?? null]))) ?: null,
                'at' => (string) ($checkpoint['checkpoint_time'] ?? ''),
                'status' => self::mapTag((string) ($checkpoint['tag'] ?? '')),
            ];
        }

        usort($events, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return [
            'status' => self::mapTag((string) ($tracking['tag'] ?? '')),
            'service' => $tracking['shipment_type'] ?? null,
            'origin' => $tracking['origin_country_iso3'] ?? null,
            'destination' => $tracking['destination_country_iso3'] ?? null,
            'eta' => $tracking['expected_delivery'] ?? null,
            'events' => $events,
        ];
    }

    public static function mapTag(string $tag): string
    {
        return match ($tag) {
            'InfoReceived', 'Pending' => 'ready',
            'InTransit' => 'in_transit',
            'OutForDelivery', 'AvailableForPickup' => 'out_for_delivery',
            'Delivered' => 'delivered',
            'Exception', 'AttemptFail' => 'delayed',
            'Expired' => 'returned',
            default => 'in_transit',
        };
    }
}
