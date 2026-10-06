<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * The only place order statuses change (section 7). Every transition is validated and audited,
 * and the linked shipment's payment-facing status follows the order (BR-10).
 */
class OrderStateMachine
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function transition(Order $order, OrderStatus $to, string $reason, ?User $actor = null): void
    {
        $from = $order->status;

        if ($from === $to && $to !== OrderStatus::MethodSelected) {
            return;
        }

        if (! $from->canTransitionTo($to)) {
            throw new DomainRuleException('invalid_transition', __('This order cannot move from :from to :to.', ['from' => $from->value, 'to' => $to->value]), 409);
        }

        $order->status = $to;
        match ($to) {
            OrderStatus::Cancelled => $order->cancelled_at = now(),
            OrderStatus::Refunded => $order->refunded_at = now(),
            default => null,
        };
        $order->save();

        $this->syncShipment($order, $to);

        $this->audit->log('order.status_changed', $order, ['status' => $from->value], ['status' => $to->value, 'reason' => $reason], $actor);
    }

    private function syncShipment(Order $order, OrderStatus $status): void
    {
        $shipmentStatus = match ($status) {
            OrderStatus::AwaitingPayment, OrderStatus::MethodSelected, OrderStatus::ProofRejected, OrderStatus::MoreInfoRequested => ShipmentStatus::AwaitingPayment,
            OrderStatus::ProofSubmitted, OrderStatus::UnderReview, OrderStatus::PartiallyPaid => ShipmentStatus::PaymentUnderReview,
            OrderStatus::Expired, OrderStatus::Cancelled => ShipmentStatus::Cancelled,
            default => null,
        };

        if ($shipmentStatus === null) {
            return;
        }

        foreach ($order->shipments()->get() as $shipment) {
            // Never overwrite a released shipment's tracking status with a payment status.
            if ($shipment->released_at !== null && $shipmentStatus !== ShipmentStatus::Cancelled) {
                continue;
            }
            $shipment->status = $shipmentStatus;
            $shipment->save();
        }
    }
}
