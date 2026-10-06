<?php

namespace App\Enums;

enum ProofStatus: string
{
    case PendingScan = 'pending_scan';
    case UnderReview = 'under_review';
    case FirstApproved = 'first_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case MoreInfo = 'more_info';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::PendingScan => __('Security scan in progress'),
            self::UnderReview => __('Under review'),
            self::FirstApproved => __('Awaiting second approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::MoreInfo => __('More information requested'),
            self::Superseded => __('Superseded'),
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PendingScan, self::UnderReview, self::FirstApproved], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::MoreInfo, self::FirstApproved => 'warning',
            default => 'info',
        };
    }
}
