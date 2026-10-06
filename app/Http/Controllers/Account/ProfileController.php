<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile, password, language, data export and deletion request (FR-106, section 11.3).
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            'logins' => $user->loginAttempts()->latest('created_at')->limit(8)->get(),
            'setup' => $request->session()->get('2fa_setup'),
            'recoveryCodes' => $request->session()->pull('2fa_recovery_codes'),
            'forceSetup' => $request->boolean('setup2fa'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            'locale' => ['required', Rule::in(config('platform.locales'))],
        ]);

        $user = $request->user();
        $user->fill($data);
        $audit->logChanges('account.profile_updated', $user);
        $user->save();

        return redirect()->route($user->locale.'.account.profile')->with('status', __('Your profile was updated.', [], $user->locale));
    }

    public function password(Request $request, AuditLogger $audit, NotificationService $notifications): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'password_changed_at' => now()])->save();

        // Ends every other session for this account.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        $audit->log('security.password_changed', $user, null, null, $user);
        $notifications->send('security.password_changed', $user, ['time' => now()->toDayDateTimeString().' UTC']);

        return back()->with('status', __('Your password was changed and other sessions were signed out.'));
    }

    /**
     * GDPR data export: the customer's own records as JSON.
     */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $user = $request->user()->load(['addresses', 'quotes', 'orders.shipments.packages', 'orders.shipments.events']);
        $audit->log('account.data_exported', $user, null, null, $user);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => $user->only(['name', 'email', 'phone', 'locale', 'created_at', 'email_verified_at']),
            'addresses' => $user->addresses->toArray(),
            'quotes' => $user->quotes->map->only(['reference', 'origin', 'destination', 'mode', 'total', 'currency', 'created_at'])->all(),
            'orders' => $user->orders->map(fn ($o) => [
                'number' => $o->number, 'status' => $o->status->value, 'total' => $o->total, 'currency' => $o->currency, 'created_at' => $o->created_at,
                'shipments' => $o->shipments->map(fn ($s) => $s->only(['tracking_number', 'mode', 'origin', 'destination', 'sender', 'recipient', 'status', 'created_at']))->all(),
            ])->all(),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'my-data-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function requestDeletion(Request $request, AuditLogger $audit, NotificationService $notifications): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password'], 'confirm' => ['accepted']]);

        $user = $request->user();
        $user->forceFill(['deletion_requested_at' => now()])->save();
        $audit->log('account.deletion_requested', $user, null, null, $user);
        $notifications->notifyStaff('admin.deletion_request', Permissions::USERS_MANAGE, ['email' => $user->email]);

        return back()->with('status', __('Your deletion request was received. We will process it within 30 days and confirm by email.'));
    }

    public static function checkPassword(Request $request): bool
    {
        return Hash::check((string) $request->input('current_password'), $request->user()->password);
    }
}
