<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Shipment;
use App\Services\Support\ClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.claims', [
            'claims' => Claim::query()->whereBelongsTo($request->user())->with(['shipment', 'logs'])->latest()->paginate(15),
            'shipments' => Shipment::query()->whereBelongsTo($request->user())->whereNotNull('released_at')->latest()->limit(50)->get(['public_id', 'tracking_number']),
        ]);
    }

    public function store(Request $request, ClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'string', 'size:26'],
            'type' => ['required', Rule::in(['lost', 'damaged', 'delayed'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'amount_claimed' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ]);

        $claims->open($request->user(), $data, $request->file('photos', []));

        return back()->with('status', __('Your claim was submitted. We will keep you informed by email.'));
    }
}
