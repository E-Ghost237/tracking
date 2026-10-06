<?php

namespace App\Jobs;

use App\Contracts\MalwareScanner;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\ReviewDecision;
use App\Models\PaymentProof;
use App\Models\PaymentReview;
use App\Services\Files\ImageSanitizer;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\OrderStateMachine;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Malware scan, metadata stripping and thumbnails for a proof, then hand-off to the review queue (section 5.5).
 * Never runs inside a web request (section 10.2).
 */
class ProcessPaymentProof implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $proofId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(MalwareScanner $scanner, ImageSanitizer $sanitizer, OrderStateMachine $states, NotificationService $notifications): void
    {
        $proof = PaymentProof::query()->with(['files', 'order.user', 'orderPayment'])->find($this->proofId);
        if ($proof === null || $proof->status !== ProofStatus::PendingScan) {
            return;
        }

        $infected = null;
        foreach ($proof->files as $file) {
            $result = $scanner->scan(Storage::disk($file->disk)->path($file->path));
            $file->scan_status = $result['clean'] ? 'clean' : 'infected';
            $file->meta = array_merge($file->meta ?? [], ['scan_signature' => $result['signature']]);
            $file->save();

            if (! $result['clean']) {
                $infected = $result['signature'];
                Storage::disk($file->disk)->delete($file->path);
            } else {
                $sanitizer->sanitize($file);
            }
        }

        $order = $proof->order;

        DB::transaction(function () use ($proof, $order, $infected, $states): void {
            if ($infected !== null) {
                $proof->forceFill(['status' => ProofStatus::Rejected, 'decided_at' => now()])->save();
                $review = new PaymentReview;
                $review->forceFill([
                    'payment_proof_id' => $proof->id,
                    'reviewer_id' => null,
                    'decision' => ReviewDecision::AutoReject,
                    'reason_code' => 'malware',
                    'note' => 'Automatic rejection: file failed the security scan ('.$infected.').',
                    'decided_at' => now(),
                ])->save();
                $proof->orderPayment->forceFill(['status' => OrderPaymentStatus::Selected])->save();
                $order->rejected_attempts++;
                $order->save();
                $states->transition($order, OrderStatus::ProofRejected, 'malware_detected');

                return;
            }

            $proof->forceFill(['status' => ProofStatus::UnderReview])->save();
            $states->transition($order, OrderStatus::UnderReview, 'scan_clean');
        });

        if ($infected !== null) {
            $notifications->send('proof.rejected', $order->user, [
                'order_number' => $order->number,
                'reference' => $order->payment_reference,
                'reason' => __('The uploaded file failed our security check. Please upload a screenshot or PDF receipt.', [], $order->user->preferredLocale()),
                'pay_url' => route($order->user->preferredLocale().'.account.orders.pay', $order),
            ]);

            return;
        }

        $notifications->send('proof.received', $order->user, [
            'order_number' => $order->number,
            'reference' => $order->payment_reference,
            'review_minutes' => (string) config('platform.settings.review_target_minutes'),
            'order_url' => route($order->user->preferredLocale().'.account.orders.pay', $order),
        ]);

        $notifications->notifyStaff('admin.proof_waiting', Permissions::PROOFS_REVIEW, [
            'order_number' => $order->number,
            'amount' => Money::format($proof->amount_paid, $proof->currency, 'en'),
            'method' => $proof->orderPayment->method->name,
            'review_url' => route('filament.admin.resources.payment-proofs.review', ['record' => $proof]),
        ]);
    }
}
