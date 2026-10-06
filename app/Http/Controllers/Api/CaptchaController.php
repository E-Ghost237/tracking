<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\CaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    public function __invoke(Request $request, CaptchaService $captcha): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['error' => ['code' => 'session_required', 'message' => __('Enable cookies to continue.')]], 400);
        }

        return response()->json($captcha->challenge($request))->header('Cache-Control', 'no-store');
    }
}
