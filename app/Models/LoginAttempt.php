<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'ip', 'user_agent', 'success', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['success' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
