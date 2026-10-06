<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\CaptchaService;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Registration (FR-100). The response is identical whether or not the email is already
 * registered: the existing owner gets an email instead, so accounts cannot be enumerated.
 */
class RegisterController extends Controller
{
    public function show(Request $request, CaptchaService $captcha): View
    {
        return view('auth.register', ['captcha' => $captcha->challenge($request)]);
    }

    public function store(Request $request, CaptchaService $captcha, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'captcha_id' => ['nullable', 'string', 'max:64'],
            'captcha_answer' => ['required', 'string', 'max:2048'],
            'website' => ['prohibited'],
        ]);

        if (! $captcha->verify($request, $data['captcha_id'] ?? null, $data['captcha_answer'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'captcha_answer']))
                ->withErrors(['captcha_answer' => __('The security check was not completed correctly. Please try again.')]);
        }

        $email = Str::lower(trim($data['email']));
        $locale = app()->getLocale();
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $existing->trashed()) {
                $notifications->send('account.register_existing', $existing, [
                    'login_url' => route($existing->preferredLocale().'.login'),
                    'reset_url' => route($existing->preferredLocale().'.password.request'),
                ]);
            }
        } else {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $locale,
            ]);
            $user->roles()->attach(Role::query()->where('slug', 'customer')->value('id'));
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route($locale.'.login')->with('status', __('Thanks! Check your inbox for a link to confirm your email address, then sign in.'));
    }
}
