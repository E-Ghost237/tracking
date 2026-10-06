<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request, LoginService $login): View|RedirectResponse
    {
        if ($login->pendingTwoFactorUser($request) === null) {
            return redirect()->route(app()->getLocale().'.login');
        }

        return view('auth.two-factor');
    }

    public function store(Request $request, LoginService $login, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $result = $login->completeTwoFactor($request, $data['code'], $twoFactor);

        return match ($result) {
            LoginService::RESULT_OK => LoginController::redirectAfterLogin($request),
            LoginService::RESULT_LOCKED => redirect()->route(app()->getLocale().'.login')->withErrors(['email' => __('Too many failed attempts. For your security, sign-in is paused for 15 minutes.')]),
            default => $login->pendingTwoFactorUser($request) === null
                ? redirect()->route(app()->getLocale().'.login')->withErrors(['email' => __('Your sign-in session expired. Please sign in again.')])
                : back()->withErrors(['code' => __('This code is not valid. Try the latest code from your app.')]),
        };
    }
}
