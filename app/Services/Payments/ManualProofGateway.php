<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Manual payments with proof validation (section 5). Selecting a method is the only
 * moment its account details are released, and only to the order's owner (FR-54 to FR-56).
 */
class ManualProofGateway implements PaymentGateway
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function requiresProof(): bool
    {
        return true;
    }

    public function select(Order $order, PaymentMethod $method, User $customer): OrderPayment
    {
        return DB::transaction(function () use ($order, $method, $customer): OrderPayment {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->assertSelectable($order, $method, $customer);

            $previous = $order->payments()->whereNull('superseded_at')->get();
            foreach ($previous as $payment) {
                $payment->forceFill(['superseded_at' => now(), 'status' => OrderPaymentStatus::Superseded])->save();
            }

            // The fee is locked once money has been received, so a partial payer is not charged twice.
            if ($order->amount_paid === 0) {
                $fee = $method->feeFor($order->subtotal);
                $order->fee = $fee;
                $order->total = $order->subtotal + $fee;
                $order->save();
            }

            $currency = strtoupper($method->currency);
            $rate = $currency === 'USD' ? 1.0 : $this->settings->exchangeRate($currency);
            if ($rate <= 0) {
                throw new DomainRuleException('currency_unavailable', __('This payment method is temporarily unavailable.'));
            }

            $payment = new OrderPayment;
            $payment->forceFill([
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'details_snapshot' => $this->snapshot($method),
                'currency' => $currency,
                'exchange_rate' => $rate,
                'fee' => $order->fee,
                'amount_expected' => Money::convert($order->balanceDue(), $currency, $rate),
                'amount_received' => 0,
                'status' => OrderPaymentStatus::Selected,
                'selected_at' => now(),
                'rate_locked_until' => $order->expires_at,
            ])->save();

            $this->states->transition($order, OrderStatus::MethodSelected, 'method_selected:'.$method->slug, $customer);

            $this->audit->log('payment.method_selected', $order, null, [
                'method' => $method->slug,
                'currency' => $currency,
                'rate' => $rate,
                'amount_expected' => $payment->amount_expected,
            ], $customer);

            return $payment;
        });
    }

    private function assertSelectable(Order $order, PaymentMethod $method, User $customer): void
    {
        if ($order->user_id !== $customer->id) {
            throw new DomainRuleException('forbidden', __('This order does not belong to you.'), 403);
        }

        if (! $order->status->acceptsMethodSelection()) {
            throw new DomainRuleException('order_not_payable', __('This order cannot be paid in its current state.'), 409);
        }

        if ($order->expires_at !== null && $order->expires_at->isPast()) {
            throw new DomainRuleException('order_expired', __('This order has expired. Please book again.'), 409);
        }

        $country = $order->shipment?->origin['country'] ?? null;
        if (! $method->allows($order->balanceDue(), $country)) {
            throw new DomainRuleException('method_not_allowed', __('This payment method is not available for this order.'), 422);
        }

        if ($method->isGiftCard()) {
            $minAge = (int) ($method->gift_card_rules['min_account_age_days'] ?? $this->settings->int('gift_card_min_account_age_days'));
            if ($customer->created_at->diffInDays(now()) < $minAge || $customer->email_verified_at === null) {
                throw new DomainRuleException('method_not_allowed', __('Gift cards are only accepted from verified accounts older than :days days.', ['days' => $minAge]), 422);
            }
        }
    }

    /**
     * Details shown to the customer, frozen for this attempt (FR-53).
     *
     * @return array<string, mixed>
     */
    private function snapshot(PaymentMethod $method): array
    {
        $fields = [];
        foreach ($method->fields as $field) {
            $fields[] = [
                'label_en' => $field->label_en,
                'label_fr' => $field->label_fr,
                'value' => (string) $field->value,
                'type' => $field->type,
            ];
        }

        return [
            'name' => $method->name,
            'kind' => $method->kind,
            'fields' => $fields,
            'instructions_en' => $method->instructions_en,
            'instructions_fr' => $method->instructions_fr,
            'requires_transaction_id' => $method->requires_transaction_id,
            'gift_card_rules' => $method->isGiftCard() ? ($method->gift_card_rules ?? []) : null,
        ];
    }
}
