<?php

namespace App\Http\Resources;

use App\Models\Quote;
use App\Support\Geo;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $breakdown = $this->price_breakdown ?? [];
        $locale = app()->getLocale();

        return [
            'id' => $this->public_id,
            'reference' => $this->reference,
            'origin' => $this->origin + ['country_name' => Geo::countryName($this->origin['country'])],
            'destination' => $this->destination + ['country_name' => Geo::countryName($this->destination['country'])],
            'mode' => $this->mode,
            'packages' => $this->packages,
            'insurance' => $this->insurance,
            'distance_km' => $this->distance_km,
            'chargeable_weight_kg' => $this->chargeable_weight_kg,
            'actual_weight_kg' => $breakdown['actual_weight_kg'] ?? null,
            'volumetric_weight_kg' => $breakdown['volumetric_weight_kg'] ?? null,
            'total' => $this->total,
            'currency' => $this->currency,
            'total_formatted' => Money::format($this->total, $this->currency, $locale),
            'price_range' => [
                'min' => $breakdown['price_min'] ?? $this->total,
                'max' => $breakdown['price_max'] ?? $this->total,
                'min_formatted' => Money::format($breakdown['price_min'] ?? $this->total, $this->currency, $locale),
                'max_formatted' => Money::format($breakdown['price_max'] ?? $this->total, $this->currency, $locale),
            ],
            'breakdown' => [
                'freight' => $breakdown['breakdown']['freight'] ?? null,
                'surcharges' => $breakdown['breakdown']['surcharges'] ?? [],
                'minimum_applied' => $breakdown['breakdown']['minimum_applied'] ?? false,
            ],
            'transit_days' => ['min' => $this->transit_min_days, 'max' => $this->transit_max_days],
            'network' => $breakdown['network'] ?? null,
            'network_label' => Geo::networkLabel($this->destination['country']),
            'note' => __('Indicative price. The final price is confirmed at booking and may change if the measured weight differs.'),
            'expires_at' => $this->expires_at->toIso8601String(),
            'is_bookable' => $this->isBookable(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
