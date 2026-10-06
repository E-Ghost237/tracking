<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\ShipmentDraft;
use App\Services\Pricing\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuoteController extends Controller
{
    /**
     * POST /api/v1/quotes: calculate and save a quote (FR-20 to FR-25).
     */
    public function store(QuoteRequest $request, QuoteService $quotes): JsonResponse
    {
        $quote = $quotes->create($request->quoteInput(), $request->user());

        if ($request->user() === null && $request->hasSession()) {
            // Lets a visitor who signs up straight away continue with this quote.
            $request->session()->put('guest_quotes', array_slice([...(array) $request->session()->get('guest_quotes', []), $quote->public_id], -10));
        }

        return (new QuoteResource($quote))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $quotes = Quote::query()->whereBelongsTo($request->user())->latest()->latest('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return QuoteResource::collection($quotes);
    }

    /**
     * POST /api/v1/quotes/{id}/book: starts a booking draft from a saved quote.
     */
    public function book(Request $request, string $quote): JsonResponse
    {
        $user = $request->user();
        $model = Quote::query()->where('public_id', $quote)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($g) => $g->whereNull('user_id')->whereIn('public_id', (array) $request->session()->get('guest_quotes', []))))
            ->firstOrFail();

        if (! $model->isBookable()) {
            return response()->json(['error' => ['code' => 'quote_not_bookable', 'message' => __('This quote has expired or was already booked. Please request a new quote.')]], 409);
        }

        $draft = ShipmentDraft::query()->create([
            'user_id' => $user->id,
            'quote_id' => $model->id,
            'step' => 1,
            'data' => ShipmentDraftController::dataFromQuote($model),
        ]);

        return response()->json([
            'draft_id' => $draft->public_id,
            'wizard_url' => route(app()->getLocale().'.account.shipments.create', ['draft' => $draft->public_id]),
        ], 201);
    }
}
