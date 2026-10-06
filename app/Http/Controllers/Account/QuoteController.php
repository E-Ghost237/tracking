<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ShipmentDraftController;
use App\Models\Quote;
use App\Models\ShipmentDraft;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Claim quotes made as a visitor in this session.
        $guest = (array) $request->session()->pull('guest_quotes', []);
        if ($guest !== []) {
            Quote::query()->whereNull('user_id')->whereIn('public_id', $guest)->update(['user_id' => $user->id]);
        }

        return view('account.quotes', ['quotes' => Quote::query()->whereBelongsTo($user)->latest()->latest('id')->paginate(15)]);
    }

    public function book(Request $request, string $quote): RedirectResponse
    {
        $model = Quote::query()->whereBelongsTo($request->user())->where('public_id', $quote)->firstOrFail();
        $locale = app()->getLocale();

        if (! $model->isBookable()) {
            return back()->with('status', __('This quote has expired or was already booked. Please request a new quote.'));
        }

        $draft = ShipmentDraft::query()->create([
            'user_id' => $request->user()->id,
            'quote_id' => $model->id,
            'step' => 1,
            'data' => ShipmentDraftController::dataFromQuote($model),
        ]);

        return redirect()->route($locale.'.account.shipments.create', ['draft' => $draft->public_id]);
    }
}
