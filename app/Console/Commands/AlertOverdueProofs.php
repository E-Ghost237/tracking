<?php

namespace App\Console\Commands;

use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use App\Services\Notifications\NotificationService;
use App\Services\Settings;
use App\Support\Permissions;
use Illuminate\Console\Command;

class AlertOverdueProofs extends Command
{
    protected $signature = 'proofs:alert-overdue';

    protected $description = 'Alert admins about proofs waiting longer than the alert threshold (FR-74).';

    public function handle(Settings $settings, NotificationService $notifications): int
    {
        $minutes = $settings->int('review_alert_minutes');
        $count = 0;

        PaymentProof::query()
            ->with('order')
            ->whereIn('status', [ProofStatus::UnderReview->value, ProofStatus::FirstApproved->value])
            ->where('submitted_at', '<', now()->subMinutes($minutes))
            ->whereNull('overdue_alerted_at')
            ->orderBy('id')
            ->each(function (PaymentProof $proof) use ($notifications, $minutes, &$count): void {
                $notifications->notifyStaff('admin.proof_overdue', Permissions::SETTINGS_MANAGE, [
                    'order_number' => $proof->order->number,
                    'minutes' => (string) max($minutes, $proof->ageInMinutes()),
                    'review_url' => route('filament.admin.resources.payment-proofs.review', ['record' => $proof]),
                ]);
                $proof->forceFill(['overdue_alerted_at' => now()])->save();
                $count++;
            });

        $this->info("Alerts sent: $count");

        return self::SUCCESS;
    }
}
