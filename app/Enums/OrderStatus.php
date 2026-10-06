<?php

namespace App\Enums;

/**
 * Order and payment states (spec sections 5.8 and 7).
 */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case MethodSelected = 'method_selected';
    case ProofSubmitted = 'proof_submitted';
    case UnderReview = 'under_review';
    case Paid = 'paid';
    case ProofRejected = 'proof_rejected';
    case MoreInfoRequested = 'more_info_requested';
    case PartiallyPaid = 'partially_paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Allowed transitions. Anything not listed here is rejected by the state machine.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::MethodSelected, self::Expired, self::Cancelled],
            self::MethodSelected => [self::MethodSelected, self::ProofSubmitted, self::Expired, self::Cancelled],
            self::ProofSubmitted => [self::UnderReview, self::ProofRejected, self::Cancelled],
            self::UnderReview => [self::Paid, self::ProofRejected, self::MoreInfoRequested, self::PartiallyPaid, self::Cancelled],
            self::ProofRejected => [self::MethodSelected, self::ProofSubmitted, self::Paid, self::Expired, self::Cancelled],
            self::MoreInfoRequested => [self::MethodSelected, self::ProofSubmitted, self::Expired, self::Cancelled],
            self::PartiallyPaid => [self::MethodSelected, self::ProofSubmitted, self::Cancelled, self::Refunded],
            self::Paid => [self::Refunded, self::Cancelled],
            self::Cancelled => [self::Refunded],
            self::Expired, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * States in which a customer may select or switch a payment method (FR-55, FR-56).
     */
    public function acceptsMethodSelection(): bool
    {
        return in_array($this, [
            self::AwaitingPayment,
            self::MethodSelected,
            self::ProofRejected,
            self::MoreInfoRequested,
            self::PartiallyPaid,
        ], true);
    }

    /**
     * States in which a customer may upload a proof for the selected method.
     */
    public function acceptsProof(): bool
    {
        return in_array($this, [
            self::MethodSelected,
            self::ProofRejected,
            self::MoreInfoRequested,
            self::PartiallyPaid,
        ], true);
    }

    /**
     * States that still wait for a proof and therefore can expire (section 5.10).
     */
    public function canExpire(): bool
    {
        return in_array($this, [
            self::AwaitingPayment,
            self::MethodSelected,
            self::ProofRejected,
            self::MoreInfoRequested,
        ], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Paid, self::Expired, self::Cancelled, self::Refunded], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => __('Awaiting payment'),
            self::MethodSelected => __('Awaiting payment'),
            self::ProofSubmitted => __('Proof submitted'),
            self::UnderReview => __('Payment under review'),
            self::Paid => __('Paid'),
            self::ProofRejected => __('Proof rejected'),
            self::MoreInfoRequested => __('More information needed'),
            self::PartiallyPaid => __('Partially paid'),
            self::Expired => __('Expired'),
            self::Cancelled => __('Cancelled'),
            self::Refunded => __('Refunded'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::UnderReview, self::ProofSubmitted => 'info',
            self::ProofRejected, self::Expired, self::Cancelled => 'danger',
            self::MoreInfoRequested, self::PartiallyPaid => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label().' ('.$case->value.')';
        }

        return $options;
    }
}
