<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingSubscription extends Model
{
    protected $fillable = ['shipment_id', 'tracking_number', 'email', 'locale', 'token_hash', 'verified_at', 'unsubscribed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
