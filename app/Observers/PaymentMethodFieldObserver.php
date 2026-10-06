<?php

namespace App\Observers;

use App\Models\PaymentMethodField;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Settings;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;

/**
 * Protects payment account details (risk R5, FR-53, P13): every change is audited and emailed
 * to all admins, and with a configured delay a new value only goes live after that delay.
 * Unpaid orders keep the snapshot they were shown.
 */
class PaymentMethodFieldObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly Settings $settings,
    ) {}

    public function saving(PaymentMethodField $field): void
    {
        if (! $field->exists || ! $field->isDirty('value')) {
            return;
        }

        $delay = $this->settings->int('payment_details_change_delay_hours');
        if ($delay > 0) {
            $field->pending_value = $field->value;
            $field->pending_effective_at = now()->addHours($delay);
            $field->value = $field->getOriginal('value');
        }
    }

    public function created(PaymentMethodField $field): void
    {
        $this->record($field, 'payment_method.field_added');
    }

    public function updated(PaymentMethodField $field): void
    {
        if ($field->wasChanged(['value', 'pending_value', 'label_en', 'label_fr', 'type'])) {
            $this->record($field, $field->wasChanged('pending_value') && ! $field->wasChanged('value') ? 'payment_method.field_change_scheduled' : 'payment_method.field_changed');
        }
    }

    public function deleted(PaymentMethodField $field): void
    {
        $this->record($field, 'payment_method.field_removed');
    }

    private function record(PaymentMethodField $field, string $action): void
    {
        $method = $field->paymentMethod()->withTrashed()->first();

        // Values are encrypted secrets: the audit log stores a fingerprint, never the value itself.
        $this->audit->log($action, $method ?? 'PaymentMethod', null, [
            'field' => $field->label_en,
            'value_fingerprint' => $field->value ? substr(hash('sha256', (string) $field->value), 0, 12) : null,
            'pending_effective_at' => $field->pending_effective_at?->toIso8601String(),
        ]);

        $this->notifications->notifyStaff('admin.payment_method_changed', Permissions::PAYMENT_METHODS_MANAGE, [
            'method' => (string) ($method?->name ?? 'unknown'),
            'actor' => (string) (Auth::user()?->email ?? 'system'),
            'time' => now()->toDayDateTimeString().' UTC',
        ]);
    }
}
