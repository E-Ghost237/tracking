<?php

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\ReviewDecision;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\PaymentReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Settings;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Verifier decisions on payment proofs (FR-70 to FR-77, BR-06, BR-07).
 */
class ProofReviewService
{
    public const RESULT_RELEASED = 'released';

    public const RESULT_FIRST_APPROVAL = 'first_approval';

    public const RESULT_PARTIAL = 'partial';

    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly OrderReleaseService $release,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, bool>  $checklist
     */
    public function approve(PaymentProof $proof, User $reviewer, array $checklist, ?string $note = null, ?int $amountReceived = null): string
    {
        $result = DB::transaction(function () use ($proof, $reviewer, $checklist, $note, $amountReceived): array {
            [$proof, $order] = $this->lockForDecision($proof, $reviewer, [ProofStatus::UnderReview, ProofStatus::FirstApproved]);

            $payment = $proof->orderPayment;
            $received = $amountReceived ?? $proof->amount_paid;
            if ($received <= 0) {
                throw new DomainRuleException('invalid_amount', __('Enter the amount received.'));
            }

            // Partial payment (FR-73): record what arrived and ask for the balance.
            if ($received < $payment->balanceDue()) {
                $this->record($proof, $reviewer, ReviewDecision::Partial, null, $note, $checklist, $received);
                $paidUsd = $this->toOrderCurrency($received, $payment->exchange_rate, $payment->currency);
                $payment->forceFill(['amount_received' => $payment->amount_received + $received, 'status' => OrderPaymentStatus::PartiallyPaid])->save();
                $order->forceFill(['amount_paid' => min($order->total, $order->amount_paid + $paidUsd)])->save();
                $proof->forceFill(['status' => ProofStatus::Approved, 'decided_at' => now()])->save();
                $this->states->transition($order, OrderStatus::PartiallyPaid, 'partial_payment', $reviewer);

                return [self::RESULT_PARTIAL, $order, $payment];
            }

            // Two-person rule above the threshold (FR-75): first approval by one user, release by another.
            if ($order->total >= $this->settings->int('two_person_threshold')) {
                $first = $proof->reviews()->where('decision', ReviewDecision::ApproveFirst->value)->first();
                if ($first === null) {
                    $this->record($proof, $reviewer, ReviewDecision::ApproveFirst, null, $note, $checklist, $received);
                    $proof->forceFill(['status' => ProofStatus::FirstApproved])->save();

                    return [self::RESULT_FIRST_APPROVAL, $order, $payment];
                }
                if ($first->reviewer_id === $reviewer->id) {
                    throw new DomainRuleException('second_approver_required', __('A different staff member must give the second approval.'), 409);
                }
            }

            $this->record($proof, $reviewer, ReviewDecision::Approve, null, $note, $checklist, $received);

            $overpaid = $received - $payment->balanceDue();
            $payment->forceFill(['amount_received' => $payment->amount_received + $received])->save();
            if ($overpaid > 0) {
                $order->forceFill(['credit' => $order->credit + $this->toOrderCurrency($overpaid, $payment->exchange_rate, $payment->currency)])->save();
            }

            $this->release->release($order, $proof, $reviewer);

            return [self::RESULT_RELEASED, $order, $payment];
        });

        [$status, $order, $payment] = $result;

        if ($status === self::RESULT_PARTIAL) {
            $this->notifications->send('proof.more_info', $order->user, [
                'order_number' => $order->number,
                'reference' => $order->payment_reference,
                'message' => __('We received :received. A balance of :balance is still due on this order.', [
                    'received' => Money::format($payment->amount_received, $payment->currency, $order->user->preferredLocale()),
                    'balance' => Money::format($payment->balanceDue(), $payment->currency, $order->user->preferredLocale()),
                ], $order->user->preferredLocale()),
                'pay_url' => route($order->user->preferredLocale().'.account.orders.pay', $order),
            ]);
        }

        return $status;
    }

    public function reject(PaymentProof $proof, User $reviewer, string $reasonCode, ?string $note = null): void
    {
        if (! array_key_exists($reasonCode, ReviewDecision::rejectReasons())) {
            throw new DomainRuleException('invalid_reason', __('Choose a rejection reason.'));
        }

        $order = DB::transaction(function () use ($proof, $reviewer, $reasonCode, $note): Order {
            [$proof, $order] = $this->lockForDecision($proof, $reviewer, [ProofStatus::UnderReview, ProofStatus::FirstApproved], approving: false);

            $this->record($proof, $reviewer, ReviewDecision::Reject, $reasonCode, $note, null, null);
            $proof->forceFill(['status' => ProofStatus::Rejected, 'decided_at' => now()])->save();
            $proof->orderPayment->forceFill(['status' => OrderPaymentStatus::Selected])->save();

            $order->rejected_attempts++;
            $order->expires_at = max($order->expires_at ?? now(), now()->addHours(24));
            if ($order->rejected_attempts >= $this->settings->int('max_rejected_attempts')) {
                $order->is_escalated = true;
            }
            $order->save();

            $this->states->transition($order, OrderStatus::ProofRejected, 'proof_rejected:'.$reasonCode, $reviewer);

            return $order;
        });

        $locale = $order->user->preferredLocale();
        $this->notifications->send('proof.rejected', $order->user, [
            'order_number' => $order->number,
            'reference' => $order->payment_reference,
            'reason' => trim(__(ReviewDecision::rejectReasons()[$reasonCode], [], $locale).($note ? ' - '.$note : '')),
            'pay_url' => route($locale.'.account.orders.pay', $order),
        ]);

        if ($order->is_escalated) {
            $this->notifications->notifyStaff('admin.order_escalated', Permissions::SETTINGS_MANAGE, [
                'order_number' => $order->number,
                'attempts' => (string) $order->rejected_attempts,
            ]);
        }
    }

    public function requestInfo(PaymentProof $proof, User $reviewer, string $message): void
    {
        $order = DB::transaction(function () use ($proof, $reviewer, $message): Order {
            [$proof, $order] = $this->lockForDecision($proof, $reviewer, [ProofStatus::UnderReview, ProofStatus::FirstApproved], approving: false);

            $this->record($proof, $reviewer, ReviewDecision::RequestInfo, 'more_info', $message, null, null);
            $proof->forceFill(['status' => ProofStatus::MoreInfo, 'decided_at' => now()])->save();
            $proof->orderPayment->forceFill(['status' => OrderPaymentStatus::Selected])->save();
            $order->expires_at = max($order->expires_at ?? now(), now()->addHours(24));
            $order->save();

            $this->states->transition($order, OrderStatus::MoreInfoRequested, 'more_info_requested', $reviewer);

            return $order;
        });

        $this->notifications->send('proof.more_info', $order->user, [
            'order_number' => $order->number,
            'reference' => $order->payment_reference,
            'message' => $message,
            'pay_url' => route($order->user->preferredLocale().'.account.orders.pay', $order),
        ]);
    }

    /**
     * Recorded correction of a rejection (BR-06): only Admin, with a reason, and only while
     * no newer proof exists on the order. The original decision stays in the history.
     */
    public function correctRejection(PaymentProof $proof, User $admin, string $reason): void
    {
        if (! $admin->hasPermission(Permissions::PROOFS_CORRECT)) {
            throw new DomainRuleException('forbidden', __('You are not allowed to correct review decisions.'), 403);
        }

        DB::transaction(function () use ($proof, $admin, $reason): void {
            /** @var PaymentProof $proof */
            $proof = PaymentProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($proof->order_id)->lockForUpdate()->firstOrFail();

            if ($proof->status !== ProofStatus::Rejected || $order->status !== OrderStatus::ProofRejected) {
                throw new DomainRuleException('not_correctable', __('Only the latest rejected proof of an unpaid order can be corrected.'), 409);
            }
            if (PaymentProof::query()->where('order_id', $order->id)->where('id', '>', $proof->id)->exists()) {
                throw new DomainRuleException('not_correctable', __('Only the latest rejected proof of an unpaid order can be corrected.'), 409);
            }
            if ($proof->files()->where('scan_status', '!=', 'clean')->exists()) {
                throw new DomainRuleException('scan_not_clean', __('A proof that failed the security scan cannot be approved.'), 409);
            }

            $this->record($proof, $admin, ReviewDecision::Correction, 'correction', $reason, null, $proof->amount_paid);
            $proof->orderPayment->forceFill(['amount_received' => $proof->orderPayment->amount_received + $proof->amount_paid])->save();
            $this->release->release($order, $proof, $admin);
        });
    }

    /**
     * Locks the proof and order and enforces who may decide (FR-76, BR-07).
     *
     * @param  array<int, ProofStatus>  $allowed
     * @return array{0: PaymentProof, 1: Order}
     */
    private function lockForDecision(PaymentProof $proof, User $reviewer, array $allowed, bool $approving = true): array
    {
        if (! $reviewer->hasPermission(Permissions::PROOFS_REVIEW)) {
            throw new DomainRuleException('forbidden', __('You are not allowed to review payments.'), 403);
        }

        /** @var PaymentProof $proof */
        $proof = PaymentProof::query()->with(['orderPayment.method', 'files'])->whereKey($proof->id)->lockForUpdate()->firstOrFail();
        $order = Order::query()->with('user')->whereKey($proof->order_id)->lockForUpdate()->firstOrFail();

        if (! in_array($proof->status, $allowed, true)) {
            throw new DomainRuleException('already_decided', __('This proof has already been decided.'), 409);
        }
        if ($order->status !== OrderStatus::UnderReview) {
            throw new DomainRuleException('order_not_under_review', __('This order is not waiting for a review.'), 409);
        }
        if ($order->user_id === $reviewer->id || $order->created_by === $reviewer->id) {
            throw new DomainRuleException('conflict_of_interest', __('You cannot review a payment on your own order or account.'), 403);
        }
        if ($approving && $proof->files->contains(fn ($file) => $file->scan_status !== 'clean')) {
            throw new DomainRuleException('scan_not_clean', __('A proof that failed the security scan cannot be approved.'), 409);
        }

        return [$proof, $order];
    }

    /**
     * @param  array<string, bool>|null  $checklist
     */
    private function record(PaymentProof $proof, User $reviewer, ReviewDecision $decision, ?string $reason, ?string $note, ?array $checklist, ?int $amountReceived): void
    {
        $review = new PaymentReview;
        $review->forceFill([
            'payment_proof_id' => $proof->id,
            'reviewer_id' => $reviewer->id,
            'decision' => $decision,
            'reason_code' => $reason,
            'note' => $note,
            'checklist' => $checklist,
            'amount_received' => $amountReceived,
            'ip' => Request::ip(),
            'decided_at' => now(),
        ])->save();

        $this->audit->log('payment.review.'.$decision->value, $proof, null, [
            'order_id' => $proof->order_id,
            'reason' => $reason,
            'note' => $note,
            'checklist' => $checklist,
            'amount_received' => $amountReceived,
        ], $reviewer);
    }

    private function toOrderCurrency(int $amount, float $rate, string $currency): int
    {
        if ($currency === 'USD' || $rate <= 0) {
            return $amount;
        }

        return (int) round(Money::toMajor($amount, $currency) / $rate * 100);
    }
}
