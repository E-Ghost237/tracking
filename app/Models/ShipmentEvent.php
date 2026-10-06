<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    /**
     * In-memory defaults matching the column defaults, so new instances are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_public' => true];

    protected $fillable = ['status', 'label', 'place', 'lat', 'lon', 'occurred_at', 'source', 'is_public', 'note', 'provider_event_id', 'created_by'];

    protected $hidden = ['id', 'shipment_id', 'created_by', 'note', 'provider_event_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
            'is_public' => 'boolean',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
