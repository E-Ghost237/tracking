<?php

namespace App\Services\Tracking;

use App\Contracts\TrackingProvider;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\TrackingCache;
use Carbon\CarbonImmutable;

/**
 * Public tracking lookups (FR-12 to FR-14).
 *
 * Source order: (1) local events entered by staff, (2) aggregator or carrier API cached
 * for 15 minutes. Unknown numbers return a clear "not found", never an invented result.
 */
class TrackingService
{
    public function __construct(
        private readonly CarrierDetector $detector,
        private readonly TrackingProvider $provider,
    ) {}

    /**
     * @param  array<int, string>  $numbers  normalised numbers from CarrierDetector::parse()
     * @return array<int, array<string, mixed>>
     */
    public function lookup(array $numbers): array
    {
        return array_map(fn (string $number) => $this->lookupOne($number), $numbers);
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupOne(string $number): array
    {
        $shipment = $this->findReleasedShipment($number);
        if ($shipment !== null) {
            return $this->presentShipment($shipment, $number);
        }

        $carrier = $this->detector->detect($number);
        if ($carrier === null || $carrier->is_own) {
            return $this->notFound($number, $carrier);
        }

        $payload = $this->fromProvider($carrier, $number);
        if ($payload === null) {
            return [
                'number' => $number,
                'found' => false,
                'carrier' => ['code' => $carrier->code, 'name' => $carrier->name],
                'message' => __('We recognised this as a :carrier number, but live :carrier data is not available here yet.', ['carrier' => $carrier->name]),
                'external_url' => $carrier->trackingUrl(str_replace('-', '', $number)),
            ];
        }

        return $this->presentProviderPayload($carrier, $number, $payload['data'], $payload['fetched_at']);
    }

    public function findReleasedShipment(string $number): ?Shipment
    {
        $compact = str_replace('-', '', $number);

        return Shipment::query()
            ->with(['carrier', 'partnerCarrier', 'events' => fn ($q) => $q->where('is_public', true)])
            ->whereNotNull('released_at')
            ->whereNotNull('tracking_number')
            ->where(fn ($q) => $q->where('tracking_number', $number)->orWhere('partner_tracking_number', $compact))
            ->first();
    }

    /**
     * Public fields only: city and country, never street, phone or email (FR-14).
     *
     * @return array<string, mixed>
     */
    public function presentShipment(Shipment $shipment, ?string $queried = null): array
    {
        $events = $shipment->events->where('is_public', true)->values();
        $carrierName = $shipment->carrier->name;

        return [
            'number' => $shipment->tracking_number,
            'queried' => $queried,
            'found' => true,
            'carrier' => ['code' => $shipment->carrier->code, 'name' => $carrierName],
            'partner' => $shipment->partnerCarrier ? [
                'name' => $shipment->partnerCarrier->name,
                'number' => $shipment->partner_tracking_number,
            ] : null,
            'service' => $shipment->service,
            'mode' => $shipment->mode,
            'status' => $shipment->status->value,
            'status_label' => $shipment->status->label(),
            'origin' => $shipment->originLabel(),
            'destination' => $shipment->destinationLabel(),
            'route' => [
                'origin' => $this->point($shipment->origin),
                'destination' => $this->point($shipment->destination),
                'current' => $shipment->current_lat !== null
                    ? ['lat' => $shipment->current_lat, 'lon' => $shipment->current_lon, 'label' => $shipment->current_place]
                    : null,
            ],
            'eta' => $shipment->eta_at?->toIso8601String(),
            'progress' => round($shipment->progress ?: $shipment->status->defaultProgress(), 3),
            'weight_kg' => round($shipment->weight_g / 1000, 2),
            'last_update' => $events->first()?->occurred_at?->toIso8601String(),
            'events' => $events->map(fn (ShipmentEvent $event) => [
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'label' => $event->label,
                'place' => $event->place,
                'at' => $event->occurred_at->toIso8601String(),
                'source' => $event->source === 'api' ? ($shipment->partnerCarrier->name ?? $carrierName) : config('platform.brand.name'),
            ])->all(),
            'data_source' => config('platform.brand.name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function presentProviderPayload(Carrier $carrier, string $number, array $data, CarbonImmutable $fetchedAt): array
    {
        $status = ShipmentStatus::tryFrom((string) ($data['status'] ?? '')) ?? ShipmentStatus::InTransit;

        return [
            'number' => $number,
            'found' => true,
            'carrier' => ['code' => $carrier->code, 'name' => $carrier->name],
            'partner' => null,
            'service' => $data['service'] ?? null,
            'mode' => null,
            'status' => $status->value,
            'status_label' => $status->label(),
            'origin' => $data['origin'] ?? null,
            'destination' => $data['destination'] ?? null,
            'route' => null,
            'eta' => $data['eta'] ?? null,
            'progress' => $status->defaultProgress(),
            'weight_kg' => null,
            'last_update' => $data['events'][0]['at'] ?? null,
            'events' => array_map(fn (array $event) => $event + ['source' => $carrier->name, 'status_label' => ShipmentStatus::tryFrom($event['status'])?->label()], $data['events'] ?? []),
            'data_source' => $carrier->name,
            'fetched_at' => $fetchedAt->toIso8601String(),
            'external_url' => $carrier->trackingUrl($number),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, fetched_at: CarbonImmutable}|null
     */
    private function fromProvider(Carrier $carrier, string $number): ?array
    {
        $cached = TrackingCache::query()->where('carrier_id', $carrier->id)->where('number', $number)->first();
        $ttl = (int) config('platform.tracking.cache_minutes', 15);

        if ($cached !== null && $cached->fetched_at->gt(now()->subMinutes($ttl))) {
            return ['data' => $cached->payload, 'fetched_at' => CarbonImmutable::parse($cached->fetched_at)];
        }

        $fresh = $this->provider->track($carrier, $number);

        if ($fresh === null) {
            // Provider error or no data: fall back to the last cached result with its age (section 10.2).
            return $cached ? ['data' => $cached->payload, 'fetched_at' => CarbonImmutable::parse($cached->fetched_at)] : null;
        }

        TrackingCache::query()->updateOrCreate(
            ['carrier_id' => $carrier->id, 'number' => $number],
            ['payload' => $fresh, 'fetched_at' => now()],
        );

        return ['data' => $fresh, 'fetched_at' => CarbonImmutable::now()];
    }

    /**
     * @return array<string, mixed>
     */
    private function notFound(string $number, ?Carrier $carrier): array
    {
        return [
            'number' => $number,
            'found' => false,
            'carrier' => $carrier ? ['code' => $carrier->code, 'name' => $carrier->name] : null,
            'message' => __('We could not find a shipment with this number. Check the number and try again. New shipments appear once payment is approved.'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $place
     * @return array{lat: float, lon: float, label: string}|null
     */
    private function point(?array $place): ?array
    {
        if (! isset($place['lat'], $place['lon'])) {
            return null;
        }

        return [
            'lat' => round((float) $place['lat'], 2),
            'lon' => round((float) $place['lon'], 2),
            'label' => trim(($place['city'] ?? '').', '.($place['country'] ?? ''), ', '),
        ];
    }
}
