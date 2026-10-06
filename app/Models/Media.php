<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use Auditable;

    protected $fillable = ['key', 'path', 'mime', 'alt_en', 'alt_fr'];
}
