<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Files\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()->whereBelongsTo($request->user())->with(['shipment', 'invoices'])
            ->latest()->latest('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, string $order): OrderResource
    {
        return new OrderResource($this->find($request, $order)->load(['shipment', 'invoices', 'currentPayment.method']));
    }

    /**
     * Signed, short-lived link to the invoice PDF (FR-104). Invoices exist only after release (BR-01).
     */
    public function invoice(Request $request, string $order, FileStorageService $files): JsonResponse
    {
        $model = $this->find($request, $order);
        $invoice = $model->invoices()->where('type', 'invoice')->whereNotNull('pdf_file_id')->with('pdf')->latest('issued_at')->first();

        if ($invoice === null) {
            return response()->json(['error' => ['code' => 'invoice_unavailable', 'message' => __('The invoice is available once payment is approved.')]], 404);
        }

        return response()->json(['number' => $invoice->number, 'url' => $files->temporaryUrl($invoice->pdf, download: true)]);
    }

    private function find(Request $request, string $id): Order
    {
        return Order::query()->whereBelongsTo($request->user())->where('public_id', $id)->firstOrFail();
    }
}
