<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Support\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    public function store(Request $request, ClaimService $claims): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'string', 'size:26'],
            'type' => ['required', Rule::in(['lost', 'damaged', 'delayed'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'amount_claimed' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ]);

        $claim = $claims->open($request->user(), $data, $request->file('photos', []));

        return response()->json(['id' => $claim->public_id, 'status' => $claim->status], 201);
    }
}
