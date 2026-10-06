<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Approve = 'approve';
    case ApproveFirst = 'approve_first';
    case Partial = 'partial';
    case Reject = 'reject';
    case RequestInfo = 'request_info';
    case Correction = 'correction';
    case AutoReject = 'auto_reject';

    /**
     * Reject reasons offered to verifiers (FR-72).
     *
     * @return array<string, string>
     */
    public static function rejectReasons(): array
    {
        return [
            'amount_mismatch' => __('Amount does not match'),
            'reference_missing' => __('Payment reference missing'),
            'date_invalid' => __('Payment date is before the order'),
            'payer_mismatch' => __('Payer name does not match'),
            'duplicate' => __('Proof already used on another order'),
            'unreadable' => __('Proof is unreadable'),
            'not_received' => __('Payment not received in our account'),
            'suspected_fraud' => __('Suspected altered or forged proof'),
            'other' => __('Other'),
        ];
    }
}
