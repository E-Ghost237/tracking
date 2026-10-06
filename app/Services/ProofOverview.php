<?php

namespace App\Services;

use App\Models\PaymentProof;
use App\Support\Money;

/**
 * Facts a verifier needs next to the proof (FR-71): what is due, what was declared,
 * how old the account is, and flags.
 */
class ProofOverview
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function for(PaymentProof $proof): array
    {
        $order = $proof->order;
        $payment = $proof->orderPayment;
        $user = $order->user;

        return [
            'due' => Money::format($payment->balanceDue() ?: $payment->amount_expected, $payment->currency, 'en'),
            'declared' => Money::format($proof->amount_paid, $proof->currency, 'en'),
            'matches' => $proof->amount_paid >= $payment->balanceDue(),
            'order_total' => Money::format($order->total, $order->currency, 'en'),
            'reference' => $order->payment_reference,
            'method' => $payment->method->name,
            'rate' => $payment->currency !== $order->currency ? $payment->exchange_rate : null,
            'order_created' => $order->created_at,
            'date_ok' => $proof->paid_on->gte($order->created_at->copy()->startOfDay()),
            'two_person' => $order->total >= $this->settings->int('two_person_threshold'),
            'account_age_days' => (int) $user->created_at->diffInDays(now()),
            'verified' => $user->email_verified_at !== null,
            'paid_orders' => $user->orders()->where('status', 'paid')->count(),
            'rejected_attempts' => $order->rejected_attempts,
            'age_minutes' => $proof->ageInMinutes(),
            'overdue' => $proof->status->isPending() && $proof->ageInMinutes() > $this->settings->int('review_alert_minutes'),
        ];
    }
}
