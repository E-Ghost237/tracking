<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a non-sequential public identifier (ULID) used in URLs and APIs,
 * so internal auto-increment ids are never exposed or enumerable.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
