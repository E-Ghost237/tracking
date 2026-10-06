<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned rate card (FR-125). Changes apply to new quotes only.
 */
class RateCard extends Model
{
    use Auditable;

    protected $fillable = ['version', 'name', 'valid_from', 'valid_to', 'is_active', 'notes', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RateLine::class);
    }

    public static function current(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->orderByDesc('version')
            ->first();
    }
}
