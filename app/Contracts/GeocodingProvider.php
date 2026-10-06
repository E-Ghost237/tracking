<?php

namespace App\Contracts;

interface GeocodingProvider
{
    /**
     * @return array<int, array{label: string, city: string, region: ?string, country: string, lat: float, lon: float}>
     */
    public function search(string $query, string $locale, int $limit = 8): array;

    /**
     * @return array{label: string, city: string, region: ?string, country: string, lat: float, lon: float}|null
     */
    public function reverse(float $lat, float $lon, string $locale): ?array;
}
