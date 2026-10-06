<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the append-only audit trail for money, status and configuration changes (section 2.1, FR-77).
 */
class AuditLogger
{
    /**
     * Attributes that must never be copied into the audit log in clear.
     */
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'value', 'pending_value', 'code', 'pin', 'api_config', 'details_snapshot'];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, Model|string|null $object = null, ?array $before = null, ?array $after = null, ?User $actor = null): AuditLog
    {
        $objectType = null;
        $objectId = null;

        if ($object instanceof Model) {
            $objectType = class_basename($object);
            $attributes = $object->getAttributes();
            $objectId = (string) ($attributes['public_id'] ?? $object->getKey());
        } elseif (is_string($object)) {
            $objectType = $object;
        }

        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'before' => $before === null ? null : $this->redact($before),
            'after' => $after === null ? null : $this->redact($after),
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512),
        ]);
    }

    /**
     * Logs the dirty attributes of a model that is about to be saved.
     */
    public function logChanges(string $action, Model $model): ?AuditLog
    {
        $dirty = $model->getDirty();
        if ($dirty === []) {
            return null;
        }

        $before = [];
        foreach (array_keys($dirty) as $key) {
            $before[$key] = $model->getOriginal($key);
        }

        return $this->log($action, $model, $before, $dirty);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::REDACTED, true)) {
                $values[$key] = $value === null ? null : '[redacted:'.substr(hash('sha256', (string) json_encode($value)), 0, 12).']';
            } elseif ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format(DATE_ATOM);
            }
        }

        return $values;
    }
}
