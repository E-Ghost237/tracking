<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Surcharge extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'type', 'value', 'basis', 'applies_to', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
