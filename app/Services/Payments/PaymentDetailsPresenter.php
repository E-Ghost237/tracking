<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Support\Money;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Builds the payload returned by POST /orders/{id}/payment-method, the only place
 * account details leave the server (FR-54, section 9.5).
 */
class PaymentDetailsPresenter
{
    /**
     * QR fields hold a payload (payment link or handle); the image is rendered here so the
     * browser receives a self-contained data URI.
     */
    private function qrImage(string $payload): string
    {
        if ($payload === '' || mb_strlen($payload) > 1000) {
            return '';
        }

        $svg = (new QRCode(new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false])))->render($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order, OrderPayment $payment, string $locale): array
    {
        $snapshot = $payment->details_snapshot ?? [];
        $instructions = $locale === 'fr'
            ? ($snapshot['instructions_fr'] ?? null) ?: ($snapshot['instructions_en'] ?? null)
            : ($snapshot['instructions_en'] ?? null);

        $fields = array_map(fn (array $field) => [
            'label' => $locale === 'fr' ? ($field['label_fr'] ?: $field['label_en']) : $field['label_en'],
            'value' => $field['type'] === 'qr' ? $this->qrImage((string) $field['value']) : $field['value'],
            'type' => $field['type'],
        ], $snapshot['fields'] ?? []);

        return [
            'payment_id' => $payment->public_id,
            'method' => [
                'name' => $snapshot['name'] ?? '',
                'kind' => $snapshot['kind'] ?? 'custom',
                'currency' => $payment->currency,
                'requires_transaction_id' => (bool) ($snapshot['requires_transaction_id'] ?? false),
            ],
            'amount_due' => [
                'amount' => $payment->balanceDue(),
                'currency' => $payment->currency,
                'formatted' => Money::format($payment->balanceDue(), $payment->currency, $locale),
            ],
            'order_total' => [
                'amount' => $order->total,
                'currency' => $order->currency,
                'formatted' => Money::format($order->total, $order->currency, $locale),
            ],
            'fee' => [
                'amount' => $order->fee,
                'currency' => $order->currency,
                'formatted' => Money::format($order->fee, $order->currency, $locale),
            ],
            'exchange_rate' => $payment->currency === $order->currency ? null : $payment->exchange_rate,
            'reference' => $order->payment_reference,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'fields' => $fields,
            'instructions' => $instructions,
            'gift_card' => $snapshot['gift_card_rules'] ?? null,
            'steps' => [
                __('Send exactly :amount using the details below.', ['amount' => Money::format($payment->balanceDue(), $payment->currency, $locale)]),
                __('Write the reference :reference in the payment note or description.', ['reference' => $order->payment_reference]),
                __('Take a screenshot or download the receipt of your payment.'),
                __('Come back to this page and upload your proof of payment.'),
            ],
        ];
    }
}
