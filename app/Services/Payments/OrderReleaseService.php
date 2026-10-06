<?php

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\GenerateShipmentDocuments;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\ShipmentDocument;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\SequenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Release of information after approval (FR-80 to FR-84). Runs inside the caller's
 * transaction: order paid, receipt, tracking number, first event, documents unlocked,
 * confirmation email queued after commit. Nothing is exposed before this point (BR-01).
 */
class OrderReleaseService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly SequenceGenerator $sequences,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function release(Order $order, PaymentProof $proof, User $approver): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Release must run inside a database transaction.');
        }

        $order->forceFill([
            'amount_paid' => $order->total,
            'paid_at' => now(),
            'receipt_number' => $this->sequences->receiptNumber(),
        ])->save();
        $this->states->transition($order, OrderStatus::Paid, 'payment_approved', $approver);

        $proof->forceFill(['status' => ProofStatus::Approved, 'decided_at' => now()])->save();
        $proof->orderPayment->forceFill(['status' => OrderPaymentStatus::Approved])->save();

        $invoice = new Invoice;
        $invoice->forceFill([
            'number' => $this->sequences->invoiceNumber(),
            'type' => 'invoice',
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => $order->currency,
            'issued_at' => now(),
            'meta' => ['receipt_number' => $order->receipt_number, 'payment_method' => $proof->orderPayment->method->name],
        ])->save();

        $shipmentIds = [];
        foreach ($order->shipments()->lockForUpdate()->get() as $shipment) {
            if ($shipment->tracking_number === null) {
                $shipment->tracking_number = $this->sequences->trackingNumber($shipment->mode);
            }
            $shipment->status = ShipmentStatus::Ready;
            $shipment->released_at = now();
            $shipment->progress = ShipmentStatus::Ready->defaultProgress();
            $shipment->current_lat = $shipment->origin['lat'] ?? null;
            $shipment->current_lon = $shipment->origin['lon'] ?? null;
            $shipment->current_place = $shipment->originLabel();
            $shipment->save();

            $event = new ShipmentEvent;
            $event->forceFill([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Ready,
                'label' => 'Shipment registered, label created',
                'place' => $shipment->originLabel(),
                'lat' => $shipment->origin['lat'] ?? null,
                'lon' => $shipment->origin['lon'] ?? null,
                'occurred_at' => now(),
                'source' => 'system',
                'is_public' => true,
                'created_by' => $approver->id,
            ])->save();

            $types = ['label', 'receipt', 'invoice'];
            if (($shipment->origin['country'] ?? null) !== ($shipment->destination['country'] ?? null)) {
                $types[] = 'commercial_invoice';
            }
            foreach ($types as $type) {
                ShipmentDocument::query()->updateOrCreate(
                    ['shipment_id' => $shipment->id, 'type' => $type],
                    ['is_released' => true],
                );
            }

            $shipmentIds[] = $shipment->id;
        }

        $this->audit->log('order.released', $order, null, [
            'receipt' => $order->receipt_number,
            'invoice' => $invoice->number,
            'tracking_numbers' => $order->shipments()->pluck('tracking_number')->all(),
        ], $approver);

        foreach ($shipmentIds as $shipmentId) {
            GenerateShipmentDocuments::dispatch($shipmentId, $invoice->id)->afterCommit();
        }

        $order->loadMissing('user');
        $locale = $order->user->preferredLocale();
        $shipment = $order->shipments()->first();

        $this->notifications->send('proof.approved', $order->user, [
            'order_number' => $order->number,
            'reference' => $order->payment_reference,
            'receipt_number' => (string) $order->receipt_number,
            'tracking_number' => (string) $shipment?->tracking_number,
            'tracking_url' => $shipment ? route($locale.'.track', ['number' => $shipment->tracking_number]) : '',
            'shipment_url' => $shipment ? route($locale.'.account.shipments.show', $shipment) : '',
        ]);
    }
}
