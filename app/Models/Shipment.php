<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use Auditable, HasPublicId, SoftDeletes;

    /**
     * In-memory defaults matching the column defaults, so new instances are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['progress' => 0, 'weight_g' => 0, 'chargeable_weight_g' => 0, 'declared_value' => 0, 'currency' => 'USD', 'insurance' => false];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service', 'mode', 'origin', 'destination', 'sender', 'recipient', 'customs', 'eta_at',
        'partner_carrier_id', 'partner_tracking_number', 'insurance',
    ];

    protected $hidden = ['id', 'user_id', 'order_id', 'carrier_id', 'partner_carrier_id', 'deleted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'origin' => 'array',
            'destination' => 'array',
            'sender' => 'array',
            'recipient' => 'array',
            'customs' => 'array',
            'insurance' => 'boolean',
            'progress' => 'float',
            'current_lat' => 'float',
            'current_lon' => 'float',
            'eta_at' => 'datetime',
            'released_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * @return BelongsTo<Carrier, $this>
     */
    public function partnerCarrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'partner_carrier_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * @return HasMany<ShipmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<ShipmentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class);
    }

    /**
     * @return HasMany<TrackingSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TrackingSubscription::class);
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null && $this->tracking_number !== null;
    }

    public function originLabel(): string
    {
        return trim(($this->origin['city'] ?? '').', '.($this->origin['country'] ?? ''), ', ');
    }

    public function destinationLabel(): string
    {
        return trim(($this->destination['city'] ?? '').', '.($this->destination['country'] ?? ''), ', ');
    }
}
