<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'ascii_name', 'country', 'region', 'lat', 'lon', 'population'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lat' => 'float', 'lon' => 'float', 'population' => 'integer'];
    }
}
