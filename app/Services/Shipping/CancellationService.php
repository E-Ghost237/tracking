<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\OrderStateMachine;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Customer cancellation before pickup (BR-09). Paid orders are refunded by Admin afterwards.
 */
class CancellationService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly NotificationService $notifications,
    ) {}

    public function cancel(Order $order, User $customer): void
    {
        $wasPaid = DB::transaction(function () use ($order, $customer): bool {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->user_id !== $customer->id) {
                throw new DomainRuleException('forbidden', __('This order does not belong to you.'), 403);
            }

            $pickedUp = $order->shipments()->whereNotIn('status', [
                ShipmentStatus::AwaitingPayment->value,
                ShipmentStatus::PaymentUnderReview->value,
                ShipmentStatus::Ready->value,
            ])->exists();

            if ($pickedUp || ! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw new DomainRuleException('not_cancellable', __('This shipment can no longer be cancelled. Please contact support.'), 409);
            }

            $wasPaid = $order->status === OrderStatus::Paid || $order->amount_paid > 0;
            $this->states->transition($order, OrderStatus::Cancelled, 'cancelled_by_customer', $customer);

            return $wasPaid;
        });

        if ($wasPaid) {
            $this->notifications->notifyStaff('admin.refund_needed', Permissions::ORDERS_REFUND, [
                'order_number' => $order->number,
            ]);
        }
    }
}
