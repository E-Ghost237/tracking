<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches geocoding results for 30 days (section 10.2) in front of any provider.
 */
class CachedGeocoder implements GeocodingProvider
{
    public function __construct(private readonly GeocodingProvider $inner, private readonly int $days = 30) {}

    public function search(string $query, string $locale, int $limit = 8): array
    {
        $key = 'geo:s:'.$locale.':'.$limit.':'.sha1(Str::lower(trim($query)));

        return Cache::remember($key, now()->addDays($this->days), fn () => $this->inner->search($query, $locale, $limit));
    }

    public function reverse(float $lat, float $lon, string $locale): ?array
    {
        $key = 'geo:r:'.$locale.':'.round($lat, 3).':'.round($lon, 3);

        return Cache::remember($key, now()->addDays($this->days), fn () => $this->inner->reverse($lat, $lon, $locale));
    }
}
