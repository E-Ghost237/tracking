<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use App\Models\City;
use App\Support\Geo;
use Illuminate\Support\Str;

/**
 * Prototype geocoder backed by the seeded cities table (FR-43).
 */
class LocalCityGeocoder implements GeocodingProvider
{
    public function search(string $query, string $locale, int $limit = 8): array
    {
        $needle = Str::lower(Str::ascii(trim($query)));
        if (mb_strlen($needle) < 2) {
            return [];
        }

        // Escape LIKE wildcards; the value itself is bound, never interpolated.
        $escaped = addcslashes($needle, '%_\\');

        return City::query()
            ->whereRaw('LOWER(ascii_name) LIKE ?', [$escaped.'%'])
            ->orderByDesc('population')
            ->limit($limit)
            ->get()
            ->map(fn (City $city) => $this->present($city, $locale))
            ->all();
    }

    public function reverse(float $lat, float $lon, string $locale): ?array
    {
        $nearest = null;
        $best = PHP_INT_MAX;

        foreach (City::query()->get(['name', 'ascii_name', 'country', 'region', 'lat', 'lon', 'population']) as $city) {
            $distance = Geo::distanceKm($lat, $lon, $city->lat, $city->lon);
            if ($distance < $best) {
                $best = $distance;
                $nearest = $city;
            }
        }

        if ($nearest === null || $best > 400) {
            return null;
        }

        return $this->present($nearest, $locale);
    }

    /**
     * @return array{label: string, city: string, region: ?string, country: string, lat: float, lon: float}
     */
    private function present(City $city, string $locale): array
    {
        return [
            'label' => $city->name.', '.Geo::countryName($city->country, $locale),
            'city' => $city->name,
            'region' => $city->region,
            'country' => $city->country,
            'lat' => $city->lat,
            'lon' => $city->lon,
        ];
    }
}
