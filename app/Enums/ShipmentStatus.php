<?php

namespace App\Enums;

/**
 * Customer-facing shipment statuses (spec appendix A, section 16.2).
 */
enum ShipmentStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentUnderReview = 'payment_under_review';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case AtCustoms = 'at_customs';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => __('Awaiting payment'),
            self::PaymentUnderReview => __('Payment under review'),
            self::Ready => __('Ready for pickup or drop-off'),
            self::PickedUp => __('Picked up'),
            self::InTransit => __('In transit'),
            self::AtCustoms => __('At customs'),
            self::OutForDelivery => __('Out for delivery'),
            self::Delivered => __('Delivered'),
            self::Delayed => __('Delayed'),
            self::Returned => __('Returned'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Statuses that staff can record as tracking events.
     *
     * @return array<int, self>
     */
    public static function eventStatuses(): array
    {
        return [
            self::Ready, self::PickedUp, self::InTransit, self::AtCustoms,
            self::OutForDelivery, self::Delivered, self::Delayed, self::Returned, self::Cancelled,
        ];
    }

    /**
     * Rough journey progress used when no explicit progress is set.
     */
    public function defaultProgress(): float
    {
        return match ($this) {
            self::AwaitingPayment, self::PaymentUnderReview, self::Cancelled => 0.0,
            self::Ready => 0.05,
            self::PickedUp => 0.15,
            self::InTransit, self::Delayed => 0.5,
            self::AtCustoms => 0.7,
            self::OutForDelivery => 0.9,
            self::Delivered, self::Returned => 1.0,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Returned, self::Cancelled], true);
    }

    /**
     * Statuses that customers subscribe to for email alerts (section 6.2).
     */
    public function notifiesSubscribers(): bool
    {
        return in_array($this, [self::PickedUp, self::InTransit, self::OutForDelivery, self::Delivered, self::Delayed, self::AtCustoms], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::Delayed, self::AtCustoms => 'warning',
            self::Returned, self::Cancelled => 'danger',
            self::AwaitingPayment, self::PaymentUnderReview => 'gray',
            default => 'info',
        };
    }

    /**
     * @param  array<int, self>|null  $cases
     * @return array<string, string>
     */
    public static function options(?array $cases = null): array
    {
        $options = [];
        foreach ($cases ?? self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
