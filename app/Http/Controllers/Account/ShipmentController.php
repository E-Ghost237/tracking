<?php

namespace App\Http\Controllers\Account;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Shipment;
use App\Models\ShipmentDraft;
use App\Support\Geo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(ShipmentStatus::cases(), 'value'))],
            'mode' => ['nullable', Rule::in(['air', 'sea', 'road', 'express'])],
            'q' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $shipments = Shipment::query()->whereBelongsTo($request->user())->with('order')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['mode'] ?? null, fn ($q, $v) => $q->where('mode', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('tracking_number', 'ilike', $like)->orWhereRaw("recipient->>'name' ilike ?", [$like])->orWhereRaw("destination->>'city' ilike ?", [$like]));
            })
            ->latest()->latest('id')->paginate(15)->withQueryString();

        return view('account.shipments.index', ['shipments' => $shipments, 'filters' => $filters]);
    }

    public function show(Request $request, string $shipment): View
    {
        $model = Shipment::query()->whereBelongsTo($request->user())->where('public_id', $shipment)
            ->with(['order.invoices', 'order.currentPayment.method', 'packages', 'events' => fn ($q) => $q->where('is_public', true), 'documents', 'carrier', 'partnerCarrier'])
            ->firstOrFail();

        return view('account.shipments.show', ['shipment' => $model]);
    }

    /**
     * Booking wizard (section 4.4). Resumes a draft when ?draft= is given.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $draftId = $request->query('draft');
        $draft = is_string($draftId) ? ShipmentDraft::query()->whereBelongsTo($user)->where('public_id', $draftId)->first() : null;

        return view('account.shipments.create', [
            'draft' => $draft,
            'drafts' => ShipmentDraft::query()->whereBelongsTo($user)->latest('updated_at')->limit(5)->get(),
            'addresses' => Address::query()->whereBelongsTo($user)->orderBy('label')->limit(100)->get(),
            'countries' => Geo::countries(),
            'categories' => config('platform.package_categories'),
        ]);
    }
}
