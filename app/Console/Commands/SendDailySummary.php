<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\PaymentReview;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDailySummary extends Command
{
    protected $signature = 'reports:daily-summary';

    protected $description = 'Send the daily summary to admins (section 14.5).';

    public function handle(NotificationService $notifications): int
    {
        $since = now()->subDay();

        $notifications->notifyStaff('admin.daily_summary', Permissions::SETTINGS_MANAGE, [
            'date' => now()->toDateString(),
            'approved' => (string) PaymentReview::query()->where('decided_at', '>=', $since)->where('decision', 'approve')->count(),
            'rejected' => (string) PaymentReview::query()->where('decided_at', '>=', $since)->whereIn('decision', ['reject', 'auto_reject'])->count(),
            'waiting' => (string) PaymentProof::query()->whereIn('status', [ProofStatus::UnderReview->value, ProofStatus::FirstApproved->value])->count(),
            'expired' => (string) Order::query()->where('status', OrderStatus::Expired->value)->where('updated_at', '>=', $since)->count(),
            'failed_jobs' => (string) DB::table('failed_jobs')->where('failed_at', '>=', $since)->count(),
        ]);

        return self::SUCCESS;
    }
}
