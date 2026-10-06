<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'email_verified' => $user->email_verified_at !== null,
            'two_factor' => $user->hasTwoFactorEnabled(),
        ]]);
    }
}
