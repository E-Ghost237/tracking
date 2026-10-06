<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Services\Auth\CaptchaService;
use App\Services\Support\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(Request $request, CaptchaService $captcha): View
    {
        return view('pages.contact', ['captcha' => $captcha->challenge($request)]);
    }

    public function store(ContactRequest $request, CaptchaService $captcha, TicketService $tickets): RedirectResponse
    {
        if (! $captcha->verify($request, $request->input('captcha_id'), $request->input('captcha_answer'))) {
            return back()->withInput($request->except('captcha_answer'))->withErrors(['captcha_answer' => __('The security check was not completed correctly. Please try again.')]);
        }

        $tickets->fromContactForm($request->validated(), $request->user(), (string) $request->ip());

        return redirect()->route(app()->getLocale().'.contact')->with('status', __('Thank you. Our team will reply by email, usually within one business day.'));
    }
}
