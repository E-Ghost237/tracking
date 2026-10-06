<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    use HasPublicId;

    protected $fillable = [
        'reference', 'user_id', 'origin', 'destination', 'packages', 'mode', 'insurance', 'declared_value',
        'chargeable_weight_kg', 'distance_km', 'price_breakdown', 'total', 'currency', 'transit_min_days',
        'transit_max_days', 'rate_card_id', 'expires_at', 'booked_at',
    ];

    protected $hidden = ['id', 'user_id', 'rate_card_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => 'array',
            'destination' => 'array',
            'packages' => 'array',
            'price_breakdown' => 'array',
            'insurance' => 'boolean',
            'declared_value' => 'integer',
            'total' => 'integer',
            'chargeable_weight_kg' => 'float',
            'expires_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isBookable(): bool
    {
        return ! $this->isExpired() && $this->booked_at === null;
    }
}
