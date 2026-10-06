<?php

namespace App\Casts;

use App\Support\FieldEncrypter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores an array attribute as encrypted JSON with the field encryption key.
 *
 * @implements CastsAttributes<array<mixed>|null, array<mixed>|null>
 */
class SecureEncryptedJson implements CastsAttributes
{
    /**
     * @return array<mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_decode(app(FieldEncrypter::class)->decrypt($value), true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(FieldEncrypter::class)->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
