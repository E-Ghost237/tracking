<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenCage adapter. The key stays on the server (FR-43).
 */
class OpenCageGeocoder implements GeocodingProvider
{
    public function __construct(private readonly string $key) {}

    public function search(string $query, string $locale, int $limit = 8): array
    {
        return $this->request(['q' => $query, 'language' => $locale, 'limit' => min($limit, 10)]);
    }

    public function reverse(float $lat, float $lon, string $locale): ?array
    {
        return $this->request(['q' => $lat.'+'.$lon, 'language' => $locale, 'limit' => 1])[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array{label: string, city: string, region: ?string, country: string, lat: float, lon: float}>
     */
    private function request(array $params): array
    {
        try {
            $response = Http::timeout(5)->connectTimeout(2)->get('https://api.opencagedata.com/geocode/v1/json', $params + ['key' => $this->key, 'no_annotations' => 1]);
            if (! $response->successful()) {
                return [];
            }

            $results = [];
            foreach ((array) $response->json('results', []) as $row) {
                $c = $row['components'] ?? [];
                $results[] = [
                    'label' => (string) ($row['formatted'] ?? ''),
                    'city' => (string) ($c['city'] ?? $c['town'] ?? $c['village'] ?? $c['county'] ?? ''),
                    'region' => $c['state'] ?? null,
                    'country' => strtoupper((string) ($c['country_code'] ?? '')),
                    'lat' => (float) ($row['geometry']['lat'] ?? 0),
                    'lon' => (float) ($row['geometry']['lng'] ?? 0),
                ];
            }

            return array_values(array_filter($results, fn ($r) => $r['country'] !== ''));
        } catch (Throwable $e) {
            Log::warning('Geocoder error', ['provider' => 'opencage', 'error' => $e->getMessage()]);

            return [];
        }
    }
}
