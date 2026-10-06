<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Models\ShipmentDraft;
use App\Services\Shipping\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    /**
     * POST /api/v1/shipments: books a completed draft and creates the order (FR-31).
     */
    public function store(Request $request, BookingService $booking): JsonResponse
    {
        $data = $request->validate(['draft_id' => ['required', 'string', 'size:26']]);
        $draft = ShipmentDraft::query()->whereBelongsTo($request->user())->where('public_id', $data['draft_id'])->firstOrFail();

        $order = $booking->book($draft, $request->user());

        return response()->json([
            'order_id' => $order->public_id,
            'order_number' => $order->number,
            'payment_reference' => $order->payment_reference,
            'status' => $order->status->value,
            'pay_url' => route(app()->getLocale().'.account.orders.pay', $order),
        ], 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'mode' => ['nullable', Rule::in(['air', 'sea', 'road', 'express'])],
            'q' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shipments = Shipment::query()
            ->whereBelongsTo($request->user())
            ->with(['carrier', 'order'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['mode'] ?? null, fn ($q, $mode) => $q->where('mode', $mode))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('tracking_number', 'ilike', '%'.addcslashes($term, '%_\\').'%'))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        return ShipmentResource::collection($shipments);
    }

    public function show(Request $request, string $shipment): ShipmentResource
    {
        $model = Shipment::query()->whereBelongsTo($request->user())->where('public_id', $shipment)
            ->with(['carrier', 'partnerCarrier', 'order', 'packages', 'events' => fn ($q) => $q->where('is_public', true), 'documents'])
            ->firstOrFail();

        return new ShipmentResource($model);
    }
}
