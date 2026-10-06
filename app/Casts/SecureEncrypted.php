<?php

namespace App\Casts;

use App\Support\FieldEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a string attribute encrypted with the field encryption key.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SecureEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return app(FieldEncrypter::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return app(FieldEncrypter::class)->encrypt((string) $value);
    }
}
