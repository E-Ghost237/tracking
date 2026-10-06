<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class TransportMode extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name_en', 'name_fr', 'multiplier', 'volumetric_divisor', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiplier' => 'float',
            'volumetric_divisor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'fr' ? $this->name_fr : $this->name_en;
    }
}
