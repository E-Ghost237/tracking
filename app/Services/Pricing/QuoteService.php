<?php

namespace App\Services\Pricing;

use App\Models\Quote;
use App\Models\User;
use App\Services\ReferenceGenerator;
use App\Services\Settings;

/**
 * Saves priced quotes with a reference and validity window (FR-25, BR-03).
 */
class QuoteService
{
    public function __construct(
        private readonly QuoteCalculator $calculator,
        private readonly ReferenceGenerator $references,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated quote input
     */
    public function create(array $input, ?User $user): Quote
    {
        $packages = array_values($input['packages']);
        $result = $this->calculator->calculate(
            $input['origin'],
            $input['destination'],
            $packages,
            $input['mode'],
            (int) ($input['declared_value'] ?? 0),
            (bool) ($input['insurance'] ?? false),
        );

        return Quote::query()->create([
            'reference' => $this->references->unique('Q', fn (string $ref) => Quote::query()->where('reference', $ref)->exists()),
            'user_id' => $user?->id,
            'origin' => $this->place($input['origin']),
            'destination' => $this->place($input['destination']),
            'packages' => $packages,
            'mode' => $input['mode'],
            'insurance' => (bool) ($input['insurance'] ?? false),
            'declared_value' => (int) ($input['declared_value'] ?? 0),
            'chargeable_weight_kg' => $result['chargeable_weight_kg'],
            'distance_km' => $result['distance_km'],
            'price_breakdown' => $result,
            'total' => $result['total'],
            'currency' => $result['currency'],
            'transit_min_days' => $result['transit_min_days'],
            'transit_max_days' => $result['transit_max_days'],
            'rate_card_id' => $result['rate_card_id'],
            'expires_at' => now()->addDays($this->settings->int('quote_validity_days')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    private function place(array $place): array
    {
        return [
            'city' => (string) $place['city'],
            'country' => strtoupper((string) $place['country']),
            'lat' => round((float) $place['lat'], 6),
            'lon' => round((float) $place['lon'], 6),
        ];
    }
}
