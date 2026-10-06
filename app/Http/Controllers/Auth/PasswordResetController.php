<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\CaptchaService;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Single-use reset links valid for 60 minutes (section 2.1). Responses never reveal
 * whether an address has an account.
 */
class PasswordResetController extends Controller
{
    public function request(Request $request, CaptchaService $captcha): View
    {
        return view('auth.forgot-password', ['captcha' => $captcha->challenge($request)]);
    }

    public function email(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'captcha_id' => ['nullable', 'string', 'max:64'],
            'captcha_answer' => ['required', 'string', 'max:2048'],
        ]);

        if (! $captcha->verify($request, $data['captcha_id'] ?? null, $data['captcha_answer'])) {
            return back()->withInput($request->only('email'))->withErrors(['captcha_answer' => __('The security check was not completed correctly. Please try again.')]);
        }

        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('status', __('If an account exists for this address, we have sent a link to reset the password. It is valid for 60 minutes.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, AuditLogger $audit, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => Str::lower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) use ($audit, $notifications): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                    'failed_logins' => 0,
                    'locked_until' => null,
                ])->save();
                $audit->log('security.password_reset', $user, null, null, $user);
                $notifications->send('security.password_changed', $user, ['time' => now()->toDayDateTimeString().' UTC']);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('This password reset link is invalid or has expired.')]);
        }

        return redirect()->route(app()->getLocale().'.login')->with('status', __('Your password has been reset. You can now sign in.'));
    }
}
