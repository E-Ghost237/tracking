<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use Auditable, HasPublicId;

    protected $fillable = ['name', 'type', 'line1', 'city', 'country', 'lat', 'lon', 'phone', 'opening_hours', 'modes', 'is_active', 'sort_order'];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'opening_hours' => 'array',
            'modes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
