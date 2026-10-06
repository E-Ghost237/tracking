<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Enums\ProofStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProofUploadRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentDetailsPresenter;
use App\Services\Payments\ProofSubmissionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer payment flow (section 5.3). Method account details are returned only by
 * POST /orders/{id}/payment-method, to the order's owner, for a payable order (FR-54 to FR-57).
 */
class PaymentController extends Controller
{
    /**
     * Enabled methods for this order: name, logo, fee. No account details.
     */
    public function methods(Request $request, string $order): JsonResponse
    {
        $model = $this->find($request, $order);
        $country = $model->shipment?->origin['country'] ?? null;
        $locale = app()->getLocale();

        $methods = PaymentMethod::query()->enabled()->get()
            ->filter(fn (PaymentMethod $m) => $m->allows($model->balanceDue() ?: $model->subtotal, $country))
            ->map(fn (PaymentMethod $m) => [
                'id' => $m->slug,
                'name' => $m->name,
                'kind' => $m->kind,
                'currency' => $m->currency,
                'fee' => $m->feeFor($model->subtotal),
                'fee_formatted' => Money::format($m->feeFor($model->subtotal), 'USD', $locale),
                'risk' => $m->risk_level,
            ])->values();

        return response()->json(['data' => $methods])->header('Cache-Control', 'no-store');
    }

    public function select(Request $request, string $order, PaymentGateway $gateway, PaymentDetailsPresenter $presenter): JsonResponse
    {
        $data = $request->validate(['method_id' => ['required', 'string', 'max:60']]);
        $model = $this->find($request, $order);
        $method = PaymentMethod::query()->enabled()->where('slug', $data['method_id'])->first();

        if ($method === null) {
            return response()->json(['error' => ['code' => 'method_not_allowed', 'message' => __('This payment method is not available for this order.')]], 422);
        }

        $payment = $gateway->select($model, $method, $request->user());

        return response()->json($presenter->present($model->refresh(), $payment, app()->getLocale()))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Details of the currently selected method, so a page refresh does not force a new selection.
     * Returns nothing once the order is no longer awaiting payment (P12).
     */
    public function current(Request $request, string $order, PaymentDetailsPresenter $presenter): JsonResponse
    {
        $model = $this->find($request, $order);
        $payment = $model->currentPayment;

        $payable = $model->status->acceptsMethodSelection() || $model->status->acceptsProof();
        if ($payment === null || ! $payable || ($model->expires_at !== null && $model->expires_at->isPast())) {
            return response()->json(['data' => null])->header('Cache-Control', 'no-store');
        }

        return response()->json(['data' => $presenter->present($model, $payment, app()->getLocale())])->header('Cache-Control', 'no-store');
    }

    public function upload(ProofUploadRequest $request, string $order, ProofSubmissionService $submissions): JsonResponse
    {
        $model = $this->find($request, $order);
        $proof = $submissions->submit($model, $request->user(), $request->safe()->except('files'), $request->file('files', []));

        return response()->json([
            'proof_id' => $proof->public_id,
            'status' => $proof->status->value,
            'message' => __('Thank you. Your proof was received and will be reviewed shortly.'),
            'review_minutes' => (int) config('platform.settings.review_target_minutes'),
        ], 201);
    }

    public function proofs(Request $request, string $order): JsonResponse
    {
        $model = $this->find($request, $order);
        $proofs = $model->proofs()->with(['reviews', 'orderPayment.method'])->latest('submitted_at')->get();

        return response()->json(['data' => $proofs->map(fn (PaymentProof $proof) => [
            'id' => $proof->public_id,
            'method' => $proof->orderPayment->method->name,
            'amount_paid' => Money::format($proof->amount_paid, $proof->currency),
            'status' => $proof->status->value,
            'status_label' => $proof->status->label(),
            'submitted_at' => $proof->submitted_at->toIso8601String(),
            'decided_at' => $proof->decided_at?->toIso8601String(),
            'reason' => $proof->status === ProofStatus::Rejected || $proof->status === ProofStatus::MoreInfo
                ? $proof->reviews->last()?->note ?? null
                : null,
        ])]);
    }

    private function find(Request $request, string $id): Order
    {
        return Order::query()->whereBelongsTo($request->user())->where('public_id', $id)->with('shipment')->firstOrFail();
    }
}
