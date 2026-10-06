<?php

namespace App\Services\Tracking;

use App\Contracts\TrackingProvider;
use App\Models\Carrier;

/**
 * Used until aggregator credentials exist: staff-entered events only (section 1.3).
 */
class NullTrackingProvider implements TrackingProvider
{
    public function track(Carrier $carrier, string $number): ?array
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
