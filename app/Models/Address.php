<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'label', 'name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code',
        'country', 'phone', 'email', 'lat', 'lon', 'is_default_sender', 'is_default_recipient',
    ];

    protected $hidden = ['id', 'user_id', 'deleted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'is_default_sender' => 'boolean',
            'is_default_recipient' => 'boolean',
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
     * Snapshot used on shipments, so later address-book edits never change a booked shipment.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return $this->only(['name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country', 'phone', 'email', 'lat', 'lon']);
    }
}
