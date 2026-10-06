<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner view of a shipment. The tracking number and documents exist only after release (BR-01).
 *
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $released = $this->isReleased();
        $locale = app()->getLocale();

        return [
            'id' => $this->public_id,
            'tracking_number' => $released ? $this->tracking_number : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'mode' => $this->mode,
            'service' => $this->service,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'sender' => $this->sender,
            'recipient' => $this->recipient,
            'weight_kg' => round($this->weight_g / 1000, 2),
            'eta' => $this->eta_at?->toIso8601String(),
            'progress' => $this->progress,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->public_id,
                'number' => $this->order->number,
                'status' => $this->order->status->value,
                'status_label' => $this->order->status->label(),
                'pay_url' => $this->order->status->isOpen() ? route($locale.'.account.orders.pay', $this->order) : null,
            ]),
            'packages' => $this->whenLoaded('packages'),
            'events' => $released ? $this->whenLoaded('events', fn () => $this->events->map(fn (ShipmentEvent $e) => [
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'label' => $e->source === 'system' ? __($e->label) : $e->label,
                'place' => $e->place,
                'at' => $e->occurred_at->toIso8601String(),
            ])) : [],
            'documents' => $released ? $this->whenLoaded('documents', fn () => $this->documents->where('is_released', true)->map(fn (ShipmentDocument $d) => [
                'type' => $d->type,
                'ready' => $d->file_id !== null,
                'url' => route($locale.'.account.shipments.document', ['shipment' => $this->public_id, 'type' => $d->type]),
            ])->values()) : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
