<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\SequenceGenerator;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Manual refunds and credit notes recorded by Admin (section 5.10, FR-123).
 */
class RefundService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly SequenceGenerator $sequences,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function refund(Order $order, User $admin, int $amount, string $method, string $reason, ?string $reference = null): Refund
    {
        if (! $admin->hasPermission(Permissions::ORDERS_REFUND)) {
            throw new DomainRuleException('forbidden', __('You are not allowed to record refunds.'), 403);
        }

        $refund = DB::transaction(function () use ($order, $admin, $amount, $method, $reason, $reference): Refund {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Cancelled, OrderStatus::PartiallyPaid], true)) {
                throw new DomainRuleException('not_refundable', __('Only paid, partially paid or cancelled orders can be refunded.'), 409);
            }

            $alreadyRefunded = (int) $order->refunds()->sum('amount');
            $refundable = $order->amount_paid + $order->credit - $alreadyRefunded;
            if ($amount <= 0 || $amount > $refundable) {
                throw new DomainRuleException('invalid_refund_amount', __('The refund cannot exceed :amount.', ['amount' => Money::format(max(0, $refundable), $order->currency)]));
            }

            $refund = new Refund;
            $refund->forceFill([
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => $order->currency,
                'method' => $method,
                'reference' => $reference,
                'reason' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();

            $creditNote = new Invoice;
            $creditNote->forceFill([
                'number' => $this->sequences->creditNoteNumber(),
                'type' => 'credit_note',
                'order_id' => $order->id,
                'amount' => -$amount,
                'currency' => $order->currency,
                'issued_at' => now(),
                'meta' => ['refund_id' => $refund->id, 'reason' => $reason],
            ])->save();

            if ($alreadyRefunded + $amount >= $order->amount_paid && $order->status !== OrderStatus::Refunded) {
                $this->states->transition($order, OrderStatus::Refunded, 'refund_recorded', $admin);
            }

            $this->audit->log('order.refunded', $order, null, ['amount' => $amount, 'method' => $method, 'reason' => $reason, 'credit_note' => $creditNote->number], $admin);

            return $refund;
        });

        $order->loadMissing('user');
        $this->notifications->send('refund.processed', $order->user, [
            'order_number' => $order->number,
            'amount' => Money::format($amount, $order->currency, $order->user->preferredLocale()),
            'method' => $method,
        ]);

        return $refund;
    }
}
