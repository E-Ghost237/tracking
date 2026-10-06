<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(app()->getLocale().'.account.dashboard');
        }

        return view('auth.verify-email');
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', __('A new verification link has been sent to your email address.'));
    }

    /**
     * The signed link proves control of the mailbox; it verifies but never signs the user in.
     */
    public function verify(Request $request, string $id, string $hash, AuditLogger $audit): RedirectResponse
    {
        $user = User::query()->where('public_id', $id)->firstOrFail();
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            $audit->log('account.email_verified', $user, null, null, $user);
        }

        $locale = $user->preferredLocale();
        $target = $request->user()?->is($user) ? route($locale.'.account.dashboard') : route($locale.'.login');

        return redirect($target)->with('status', __('Your email address is confirmed.', [], $locale));
    }
}
