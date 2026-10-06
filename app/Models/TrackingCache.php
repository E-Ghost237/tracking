<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingCache extends Model
{
    public $timestamps = false;

    protected $table = 'tracking_cache';

    protected $fillable = ['carrier_id', 'number', 'payload', 'fetched_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'fetched_at' => 'datetime'];
    }
}
