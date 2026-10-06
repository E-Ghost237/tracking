<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddressRequest;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Address book (FR-103). Globe pins are stored only when the customer saves an address (FR-45).
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::query()->whereBelongsTo($request->user())->orderByDesc('is_default_sender')->orderBy('label')->orderBy('id')->limit(200)->get();

        return response()->json(['data' => $addresses]);
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $user = $request->user();
        if (Address::query()->whereBelongsTo($user)->count() >= 200) {
            return response()->json(['error' => ['code' => 'limit_reached', 'message' => __('You can save up to 200 addresses.')]], 422);
        }

        $address = DB::transaction(function () use ($request, $user): Address {
            $address = new Address($request->validated());
            $address->user()->associate($user);
            $address->save();
            $this->normaliseDefaults($address);

            return $address;
        });

        return response()->json(['data' => $address->fresh()], 201);
    }

    public function update(AddressRequest $request, string $address): JsonResponse
    {
        $model = Address::query()->whereBelongsTo($request->user())->where('public_id', $address)->firstOrFail();

        DB::transaction(function () use ($request, $model): void {
            $model->fill($request->validated())->save();
            $this->normaliseDefaults($model);
        });

        return response()->json(['data' => $model->fresh()]);
    }

    public function destroy(Request $request, string $address): JsonResponse
    {
        Address::query()->whereBelongsTo($request->user())->where('public_id', $address)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    private function normaliseDefaults(Address $address): void
    {
        foreach (['is_default_sender', 'is_default_recipient'] as $flag) {
            if ($address->{$flag}) {
                Address::query()->where('user_id', $address->user_id)->whereKeyNot($address->id)->update([$flag => false]);
            }
        }
    }
}
