<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->public_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subtotal' => Money::format($this->subtotal, $this->currency, $locale),
            'fee' => Money::format($this->fee, $this->currency, $locale),
            'total' => Money::format($this->total, $this->currency, $locale),
            'total_minor' => $this->total,
            'balance_due' => Money::format($this->balanceDue(), $this->currency, $locale),
            'currency' => $this->currency,
            'payment_reference' => $this->payment_reference,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'receipt_number' => $this->receipt_number,
            'shipment_id' => $this->whenLoaded('shipment', fn () => $this->shipment?->public_id),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn (Invoice $i) => [
                'id' => $i->public_id,
                'number' => $i->number,
                'type' => $i->type,
                'amount' => Money::format($i->amount, $i->currency, $locale),
                'issued_at' => $i->issued_at->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
