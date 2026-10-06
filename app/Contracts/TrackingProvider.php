<?php

namespace App\Contracts;

use App\Models\Carrier;

/**
 * Source of live carrier scans (aggregator or carrier API). Implementations return a
 * normalised payload, or null when the provider has no data for the number.
 */
interface TrackingProvider
{
    /**
     * @return array{status: string, service: ?string, origin: ?string, destination: ?string, eta: ?string, events: array<int, array{label: string, place: ?string, at: string, status: string}>}|null
     */
    public function track(Carrier $carrier, string $number): ?array;

    public function isConfigured(): bool;
}
