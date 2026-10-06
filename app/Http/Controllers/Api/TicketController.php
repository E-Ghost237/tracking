<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()->whereBelongsTo($request->user())->latest('updated_at')->paginate(20);

        return response()->json($tickets->through(fn (Ticket $t) => [
            'id' => $t->public_id, 'subject' => $t->subject, 'status' => $t->status, 'updated_at' => $t->updated_at?->toIso8601String(),
        ]));
    }

    public function store(Request $request, TicketService $tickets): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'shipment_id' => ['nullable', 'string', 'size:26'],
        ]);

        $ticket = $tickets->open($request->user(), $data);

        return response()->json(['id' => $ticket->public_id], 201);
    }

    public function reply(Request $request, string $ticket, TicketService $tickets): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        $model = Ticket::query()->whereBelongsTo($request->user())->where('public_id', $ticket)->firstOrFail();
        $tickets->customerReply($model, $request->user(), $data['message']);

        return response()->json(['status' => $model->fresh()->status], 201);
    }
}
