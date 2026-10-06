<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Writes every create, update and delete of a configuration or business record to the
 * append-only audit log with before and after values (section 2.1, FR-130).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            app(AuditLogger::class)->log(self::auditName($model).'.created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $before = [];
            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getRawOriginal($key);
            }
            app(AuditLogger::class)->log(self::auditName($model).'.updated', $model, $before, $changes);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->log(self::auditName($model).'.deleted', $model, $model->getAttributes(), null);
        });
    }

    private static function auditName(object $model): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)) ?? class_basename($model));
    }
}
