<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TOTP setup (FR-106, FR-144). The secret lives in the session until confirmed with a valid code.
 */
class TwoFactorController extends Controller
{
    public function start(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $secret = $twoFactor->generateSecret();
        $request->session()->put('2fa_setup', [
            'secret' => $secret,
            'qr' => $twoFactor->qrSvg($request->user(), $secret),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return back();
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $setup = $request->session()->get('2fa_setup');

        if (! is_array($setup) || $setup['expires'] < now()->timestamp) {
            $request->session()->forget('2fa_setup');

            return back()->withErrors(['code' => __('The setup expired. Please start again.')]);
        }

        if (! $twoFactor->verifySecret($setup['secret'], (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('This code is not valid. Try the latest code from your app.')]);
        }

        $codes = $twoFactor->enable($request->user(), $setup['secret']);
        $request->session()->forget('2fa_setup');
        $request->session()->put('2fa_recovery_codes', $codes);
        $request->session()->regenerate();

        return back()->with('status', __('Two-factor authentication is on. Store your recovery codes somewhere safe.'));
    }

    public function disable(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password'], 'code' => ['required', 'string', 'max:20']]);
        $user = $request->user();

        if ($user->isStaff()) {
            return back()->withErrors(['code' => __('Staff accounts must keep two-factor authentication on.')]);
        }

        if (! $twoFactor->verify($user, (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('This code is not valid. Try the latest code from your app.')]);
        }

        $twoFactor->disable($user);

        return back()->with('status', __('Two-factor authentication is off.'));
    }
}
