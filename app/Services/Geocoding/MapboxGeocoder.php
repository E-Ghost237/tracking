<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mapbox Geocoding v6 adapter. The token stays on the server (FR-43).
 */
class MapboxGeocoder implements GeocodingProvider
{
    public function __construct(private readonly string $token) {}

    public function search(string $query, string $locale, int $limit = 8): array
    {
        return $this->request('https://api.mapbox.com/search/geocode/v6/forward', [
            'q' => $query,
            'language' => $locale,
            'limit' => min($limit, 10),
            'types' => 'place,locality,address,postcode',
        ]);
    }

    public function reverse(float $lat, float $lon, string $locale): ?array
    {
        return $this->request('https://api.mapbox.com/search/geocode/v6/reverse', [
            'latitude' => $lat,
            'longitude' => $lon,
            'language' => $locale,
            'limit' => 1,
        ])[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array{label: string, city: string, region: ?string, country: string, lat: float, lon: float}>
     */
    private function request(string $url, array $params): array
    {
        try {
            $response = Http::timeout(5)->connectTimeout(2)->get($url, $params + ['access_token' => $this->token]);
            if (! $response->successful()) {
                return [];
            }

            $results = [];
            foreach ((array) $response->json('features', []) as $feature) {
                $props = $feature['properties'] ?? [];
                $context = $props['context'] ?? [];
                $results[] = [
                    'label' => (string) ($props['full_address'] ?? $props['name'] ?? ''),
                    'city' => (string) ($context['place']['name'] ?? $props['name'] ?? ''),
                    'region' => $context['region']['name'] ?? null,
                    'country' => strtoupper((string) ($context['country']['country_code'] ?? '')),
                    'lat' => (float) ($props['coordinates']['latitude'] ?? 0),
                    'lon' => (float) ($props['coordinates']['longitude'] ?? 0),
                ];
            }

            return array_values(array_filter($results, fn ($r) => $r['country'] !== ''));
        } catch (Throwable $e) {
            Log::warning('Geocoder error', ['provider' => 'mapbox', 'error' => $e->getMessage()]);

            return [];
        }
    }
}
