<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'countries'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['countries' => 'array'];
    }
}
