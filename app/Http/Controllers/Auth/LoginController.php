<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\CaptchaService;
use App\Services\Auth\LoginService;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function show(Request $request, LoginService $login, CaptchaService $captcha): View
    {
        $needsCaptcha = $login->recentFailures($request) >= (int) config('platform.security.login_captcha_after', 3);

        return view('auth.login', ['captcha' => $needsCaptcha ? $captcha->challenge($request) : null]);
    }

    public function store(Request $request, LoginService $login, CaptchaService $captcha): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $locale = app()->getLocale();
        if ($login->recentFailures($request) >= (int) config('platform.security.login_captcha_after', 3)
            && ! $captcha->verify($request, $request->input('captcha_id'), $request->input('captcha_answer'))) {
            return back()->withInput($request->only('email'))->withErrors(['captcha_answer' => __('Please complete the security check.')]);
        }

        $result = $login->attempt($request, $data['email'], $data['password'], (bool) ($data['remember'] ?? false));

        return match ($result) {
            LoginService::RESULT_OK => $this->redirectAfterLogin($request),
            LoginService::RESULT_TWO_FACTOR => redirect()->route($locale.'.two-factor'),
            LoginService::RESULT_LOCKED => back()->withInput($request->only('email'))->withErrors(['email' => __('Too many failed attempts. For your security, sign-in is paused for 15 minutes.')]),
            default => back()->withInput($request->only('email'))->withErrors(['email' => __('These credentials do not match our records.')]),
        };
    }

    public function destroy(Request $request, LoginService $login): RedirectResponse
    {
        $login->logout($request);

        return redirect()->route(app()->getLocale().'.home');
    }

    public static function redirectAfterLogin(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isStaff() && ! $user->hasTwoFactorEnabled()) {
            // Staff must set up 2FA before the back-office opens (FR-144).
            return redirect()->route($user->preferredLocale().'.account.profile', ['setup2fa' => 1])
                ->with('status', __('Staff accounts must enable two-factor authentication before using the back-office.'));
        }

        if ($user->hasPermission(Permissions::ADMIN_ACCESS)) {
            return redirect()->intended('/'.trim((string) config('platform.admin.path'), '/'));
        }

        return redirect()->intended(route($user->preferredLocale().'.account.dashboard'));
    }
}
