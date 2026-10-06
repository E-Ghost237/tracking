<?php

namespace App\Console\Commands;

use App\Models\PaymentMethodField;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class PromotePaymentDetails extends Command
{
    protected $signature = 'payments:promote-details';

    protected $description = 'Make delayed payment detail changes live once their delay has passed (risk R5).';

    public function handle(AuditLogger $audit): int
    {
        PaymentMethodField::query()
            ->whereNotNull('pending_effective_at')
            ->where('pending_effective_at', '<=', now())
            ->each(function (PaymentMethodField $field) use ($audit): void {
                // Bypass the observer's delay logic: this IS the scheduled promotion.
                PaymentMethodField::withoutEvents(function () use ($field): void {
                    $field->forceFill(['value' => $field->pending_value, 'pending_value' => null, 'pending_effective_at' => null])->save();
                });
                $audit->log('payment_method.field_change_live', $field->paymentMethod()->withTrashed()->first(), null, ['field' => $field->label_en]);
            });

        return self::SUCCESS;
    }
}
