<?php

namespace App\Services\Documents;

use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\StoredFile;
use App\Services\Files\FileStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * PDF label, invoice, commercial invoice and receipt (FR-33). Generated in a queued job after release.
 */
class DocumentGenerator
{
    public function __construct(private readonly FileStorageService $files) {}

    public function generate(Shipment $shipment, string $type, ?Invoice $invoice): StoredFile
    {
        $shipment->loadMissing(['order.user', 'packages', 'carrier']);
        $locale = $shipment->order?->user?->preferredLocale() ?? 'en';
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $data = [
                'shipment' => $shipment,
                'order' => $shipment->order,
                'invoice' => $invoice,
                'brand' => config('platform.brand'),
                'barcode' => $this->barcode((string) $shipment->tracking_number),
                'qr' => $this->qr(route($locale.'.track', ['number' => $shipment->tracking_number])),
            ];

            $pdf = Pdf::loadView('pdf.'.$type, $data)
                ->setPaper($type === 'label' ? [0, 0, 288, 432] : 'a4')
                ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);

            $name = match ($type) {
                'label' => 'label-'.$shipment->tracking_number,
                'invoice' => 'invoice-'.($invoice?->number ?? $shipment->tracking_number),
                'commercial_invoice' => 'commercial-invoice-'.$shipment->tracking_number,
                default => 'receipt-'.$shipment->order?->receipt_number,
            };

            return $this->files->storeGenerated($pdf->output(), 'documents', 'application/pdf', 'pdf', $shipment->order?->user, $name.'.pdf');
        } finally {
            app()->setLocale($previous);
        }
    }

    private function barcode(string $value): string
    {
        $barcode = (new TypeCode128)->getBarcode($value);
        $png = (new PngRenderer)->render($barcode, $barcode->getWidth() * 2, 60);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function qr(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 4,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($url);
    }
}
