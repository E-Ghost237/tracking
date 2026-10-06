<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.support.index', [
            'tickets' => Ticket::query()->whereBelongsTo($request->user())->latest('updated_at')->paginate(15),
            'shipments' => Shipment::query()->whereBelongsTo($request->user())->whereNotNull('tracking_number')->latest()->limit(50)->get(['public_id', 'tracking_number']),
            'preselect' => $request->query('shipment'),
        ]);
    }

    public function store(Request $request, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'shipment_id' => ['nullable', 'string', 'size:26'],
        ]);

        $ticket = $tickets->open($request->user(), $data);

        return redirect()->route(app()->getLocale().'.account.support.show', $ticket)->with('status', __('Your message was sent. We usually reply within one business day.'));
    }

    public function show(Request $request, string $ticket): View
    {
        $model = Ticket::query()->whereBelongsTo($request->user())->where('public_id', $ticket)->with(['messages', 'shipment'])->firstOrFail();

        return view('account.support.show', ['ticket' => $model]);
    }

    public function reply(Request $request, string $ticket, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        $model = Ticket::query()->whereBelongsTo($request->user())->where('public_id', $ticket)->firstOrFail();
        $tickets->customerReply($model, $request->user(), $data['message']);

        return back()->with('status', __('Your reply was sent.'));
    }
}
