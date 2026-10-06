<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Shipment;
use App\Services\Documents\DocumentGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the released documents of a shipment. Idempotent: existing files are kept.
 */
class GenerateShipmentDocuments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $shipmentId, public ?int $invoiceId = null) {}

    public function handle(DocumentGenerator $generator): void
    {
        $shipment = Shipment::query()->with('documents')->find($this->shipmentId);
        if ($shipment === null || ! $shipment->isReleased()) {
            return;
        }

        $invoice = $this->invoiceId ? Invoice::query()->find($this->invoiceId) : null;

        foreach ($shipment->documents as $document) {
            if ($document->file_id !== null || ! $document->is_released) {
                continue;
            }

            $file = $generator->generate($shipment, $document->type, $invoice);
            $document->file_id = $file->id;
            $document->save();

            if ($document->type === 'invoice' && $invoice !== null && $invoice->pdf_file_id === null) {
                $invoice->forceFill(['pdf_file_id' => $file->id])->save();
            }
        }
    }
}
