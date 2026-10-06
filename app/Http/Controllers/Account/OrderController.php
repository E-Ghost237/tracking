<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Files\FileStorageService;
use App\Services\Shipping\CancellationService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()->whereBelongsTo($request->user())->with(['shipment', 'invoices'])->latest()->latest('id')->paginate(15);

        return view('account.orders.index', ['orders' => $orders]);
    }

    /**
     * Pay page (section 5.3). Lists methods by name and logo only: no account details are
     * present in the page source (FR-54, P1). Details arrive only after selection.
     */
    public function pay(Request $request, string $order): View
    {
        $model = Order::query()->whereBelongsTo($request->user())->where('public_id', $order)
            ->with(['shipment', 'proofs.reviews', 'proofs.orderPayment.method', 'currentPayment.method'])->firstOrFail();

        $country = $model->shipment?->origin['country'] ?? null;
        $methods = PaymentMethod::query()->enabled()->get()
            ->filter(fn (PaymentMethod $m) => $m->allows($model->balanceDue() ?: $model->subtotal, $country))
            ->map(fn (PaymentMethod $m) => [
                'id' => $m->slug,
                'name' => $m->name,
                'kind' => $m->kind,
                'currency' => $m->currency,
                'fee' => Money::format($m->feeFor($model->subtotal)),
                'risk' => $m->risk_level,
            ])->values();

        return view('account.orders.pay', [
            'order' => $model,
            'methods' => $methods,
            'selected' => $model->currentPayment?->method?->slug,
        ]);
    }

    public function invoice(Request $request, string $order, string $invoice, FileStorageService $files): RedirectResponse
    {
        $model = Order::query()->whereBelongsTo($request->user())->where('public_id', $order)->firstOrFail();
        $document = $model->invoices()->where('public_id', $invoice)->whereNotNull('pdf_file_id')->with('pdf')->firstOrFail();

        return redirect()->away($files->temporaryUrl($document->pdf, download: true));
    }

    public function cancel(Request $request, string $order, CancellationService $cancellation): RedirectResponse
    {
        $model = Order::query()->whereBelongsTo($request->user())->where('public_id', $order)->firstOrFail();
        $cancellation->cancel($model, $request->user());

        return redirect()->route(app()->getLocale().'.account.orders')->with('status', __('Your order was cancelled.'));
    }
}
