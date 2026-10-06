<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateLine extends Model
{
    use Auditable;

    protected $fillable = [
        'rate_card_id', 'zone_from', 'zone_to', 'mode', 'base_fee', 'weight_from_kg', 'weight_to_kg',
        'price_per_kg', 'transit_min_days', 'transit_max_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_fee' => 'integer',
            'price_per_kg' => 'integer',
            'weight_from_kg' => 'float',
            'weight_to_kg' => 'float',
            'transit_min_days' => 'integer',
            'transit_max_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RateCard, $this>
     */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }
}
