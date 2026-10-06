<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Services\Auth\CaptchaService;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __invoke(ContactRequest $request, CaptchaService $captcha, TicketService $tickets): JsonResponse
    {
        if (! $request->hasSession() || ! $captcha->verify($request, $request->input('captcha_id'), $request->input('captcha_answer'))) {
            return response()->json(['error' => ['code' => 'captcha_failed', 'message' => __('The security check was not completed correctly. Please try again.')]], 422);
        }

        $tickets->fromContactForm($request->validated(), $request->user(), (string) $request->ip());

        return response()->json(['message' => __('Thank you. Our team will reply by email, usually within one business day.')], 201);
    }
}
