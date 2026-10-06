<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DraftStepRequest;
use App\Models\Quote;
use App\Models\ShipmentDraft;
use App\Services\Pricing\QuoteCalculator;
use App\Services\Shipping\BookingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Booking wizard drafts, saved after each step and resumable (FR-30).
 */
class ShipmentDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Keep the number of open drafts per customer bounded.
        if (ShipmentDraft::query()->whereBelongsTo($user)->count() >= 20) {
            ShipmentDraft::query()->whereBelongsTo($user)->oldest('updated_at')->first()?->delete();
        }

        $draft = ShipmentDraft::query()->create(['user_id' => $user->id, 'step' => 1, 'data' => []]);

        return response()->json($this->present($draft), 201);
    }

    public function show(Request $request, string $draft): JsonResponse
    {
        return response()->json($this->present($this->find($request, $draft)));
    }

    public function update(DraftStepRequest $request, string $draft): JsonResponse
    {
        $model = $this->find($request, $draft);
        $step = (string) $request->input('step');
        $index = DraftStepRequest::STEPS[$step];

        if ($index > $model->step + 1 && $step !== 'route') {
            throw new DomainRuleException('step_out_of_order', __('Please complete the previous steps first.'));
        }

        $data = $model->data ?? [];
        $data[$step] = $request->stepData();

        if ($step === 'packages' || $step === 'route') {
            // Changing route or parcels invalidates a previously chosen service price.
            unset($data['review']);
        }

        $model->data = $data;
        $model->step = max($model->step, min(5, $index + 1));
        $model->save();

        return response()->json($this->present($model));
    }

    /**
     * Live price for each mode for the current route and packages (step 3).
     */
    public function price(Request $request, string $draft, QuoteCalculator $calculator, BookingService $booking): JsonResponse
    {
        $model = $this->find($request, $draft);
        $data = $model->data ?? [];
        if (empty($data['route']) || empty($data['packages'])) {
            throw new DomainRuleException('draft_incomplete', __('Please complete the route and packages first.'));
        }

        $insurance = $request->boolean('insurance', (bool) ($data['service']['insurance'] ?? false));
        $declared = Money::fromMajor(array_sum(array_column($data['packages'], 'value')));
        $options = [];

        foreach (['air', 'express', 'sea', 'road'] as $mode) {
            $input = $booking->quoteInput(['route' => $data['route'], 'packages' => $data['packages'], 'service' => ['mode' => $mode, 'insurance' => $insurance, 'declared_value' => $declared]]);
            try {
                $result = $calculator->calculate($input['origin'], $input['destination'], $input['packages'], $mode, $declared, $insurance);
                $options[] = [
                    'mode' => $mode,
                    'available' => true,
                    'total' => $result['total'],
                    'total_formatted' => Money::format($result['total']),
                    'transit_min_days' => $result['transit_min_days'],
                    'transit_max_days' => $result['transit_max_days'],
                    'chargeable_weight_kg' => $result['chargeable_weight_kg'],
                    'breakdown' => $result['breakdown'],
                    'network_label' => $result['network_label'],
                    'distance_km' => $result['distance_km'],
                ];
            } catch (DomainRuleException $e) {
                $options[] = ['mode' => $mode, 'available' => false, 'reason' => $e->getMessage()];
            }
        }

        return response()->json(['options' => $options, 'declared_value' => $declared]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dataFromQuote(Quote $quote): array
    {
        $packages = array_map(fn (array $p) => $p + ['description' => '', 'value' => 0, 'category' => ''], $quote->packages);

        return [
            'prefill' => [
                'origin' => $quote->origin,
                'destination' => $quote->destination,
                'packages' => $packages,
                'mode' => $quote->mode,
                'insurance' => $quote->insurance,
                'quote_reference' => $quote->reference,
            ],
        ];
    }

    private function find(Request $request, string $id): ShipmentDraft
    {
        return ShipmentDraft::query()->whereBelongsTo($request->user())->where('public_id', $id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ShipmentDraft $draft): array
    {
        return [
            'id' => $draft->public_id,
            'step' => $draft->step,
            'data' => $draft->data ?? [],
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ];
    }
}
