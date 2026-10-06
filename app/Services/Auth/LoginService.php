<?php

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password login with account lockout (5 failures, 15 minutes), attempt history,
 * TOTP second step and new-device alerts (section 2.1).
 */
class LoginService
{
    public const RESULT_OK = 'ok';

    public const RESULT_INVALID = 'invalid';

    public const RESULT_LOCKED = 'locked';

    public const RESULT_TWO_FACTOR = 'two_factor';

    private const PENDING_KEY = 'auth.pending_2fa';

    public function __construct(private readonly NotificationService $notifications) {}

    public function attempt(Request $request, string $email, string $password, bool $remember): string
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // Spend the same time as a real check, and lock unknown addresses exactly like real
            // ones, so neither timing nor the lockout message reveals whether an account exists.
            Hash::check($password, Cache::rememberForever('auth.timing_hash', fn () => Hash::make(Str::random(40))));
            $this->record($request, null, $email, false, 'unknown');
            $key = 'login-unknown:'.hash('sha256', $email);
            $failures = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $failures, now()->addMinutes((int) config('platform.security.lockout_minutes', 15)));

            return $failures >= (int) config('platform.security.lockout_attempts', 5) ? self::RESULT_LOCKED : self::RESULT_INVALID;
        }

        if ($user->isLocked()) {
            $this->record($request, $user, $email, false, 'locked');

            return self::RESULT_LOCKED;
        }

        if (! Hash::check($password, $user->password) || ! $user->isActive()) {
            $this->registerFailure($request, $user, $email);

            return $user->isLocked() ? self::RESULT_LOCKED : self::RESULT_INVALID;
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_KEY, [
                'user_id' => $user->id,
                'remember' => $remember,
                'expires' => now()->addMinutes(5)->timestamp,
                'tries' => 0,
            ]);
            $this->record($request, $user, $email, true, 'password_ok_2fa_pending');

            return self::RESULT_TWO_FACTOR;
        }

        $this->complete($request, $user, $remember);

        return self::RESULT_OK;
    }

    /**
     * Failed attempts from this address in the last 15 minutes, used to require a CAPTCHA.
     */
    public function recentFailures(Request $request): int
    {
        return LoginAttempt::query()
            ->where('ip', (string) $request->ip())
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
    }

    public function pendingTwoFactorUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return User::query()->find($pending['user_id']);
    }

    /**
     * Second step. Wrong codes count towards the same lockout as wrong passwords.
     */
    public function completeTwoFactor(Request $request, string $code, TwoFactorService $twoFactor): string
    {
        $user = $this->pendingTwoFactorUser($request);
        if ($user === null || $user->isLocked() || ! $user->isActive()) {
            $request->session()->forget(self::PENDING_KEY);

            return $user?->isLocked() ? self::RESULT_LOCKED : self::RESULT_INVALID;
        }

        if (! $twoFactor->verify($user, $code)) {
            $this->registerFailure($request, $user, $user->email, '2fa_failed');
            $pending = $request->session()->get(self::PENDING_KEY);
            $pending['tries'] = ($pending['tries'] ?? 0) + 1;
            if ($pending['tries'] >= 5 || $user->isLocked()) {
                $request->session()->forget(self::PENDING_KEY);

                return self::RESULT_LOCKED;
            }
            $request->session()->put(self::PENDING_KEY, $pending);

            return self::RESULT_INVALID;
        }

        $remember = (bool) ($request->session()->get(self::PENDING_KEY)['remember'] ?? false);
        $request->session()->forget(self::PENDING_KEY);
        $this->complete($request, $user, $remember);

        return self::RESULT_OK;
    }

    public function complete(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->record($request, $user, $user->email, true, null);
        $this->checkDevice($request, $user);
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function registerFailure(Request $request, User $user, string $email, string $reason = 'bad_password'): void
    {
        $user->failed_logins++;
        if ($user->failed_logins >= (int) config('platform.security.lockout_attempts', 5)) {
            $user->locked_until = now()->addMinutes((int) config('platform.security.lockout_minutes', 15));
            $user->failed_logins = 0;
            $reason .= ':locked';
        }
        $user->save();

        $this->record($request, $user, $email, false, $reason);
    }

    private function record(Request $request, ?User $user, string $email, bool $success, ?string $reason): void
    {
        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => mb_substr($email, 0, 190),
            'ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'success' => $success,
            'reason' => $reason,
        ]);
    }

    /**
     * Emails the user when they sign in from a device we have not seen before (section 6.2).
     */
    private function checkDevice(Request $request, User $user): void
    {
        $deviceId = (string) $request->cookie('device_id');
        if (strlen($deviceId) !== 40) {
            $deviceId = Str::random(40);
            Cookie::queue(Cookie::make('device_id', $deviceId, 60 * 24 * 400, '/', null, null, true, false, 'lax'));
        }

        $fingerprint = hash('sha256', $deviceId);
        $known = UserDevice::query()->where('user_id', $user->id)->where('fingerprint', $fingerprint)->first();
        $hasDevices = UserDevice::query()->where('user_id', $user->id)->exists();

        UserDevice::query()->updateOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $fingerprint],
            ['user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'ip' => $request->ip(), 'last_seen_at' => now()],
        );

        if ($known === null && $hasDevices) {
            $this->notifications->send('security.new_login', $user, [
                'ip' => (string) $request->ip(),
                'device' => mb_substr((string) $request->userAgent(), 0, 120),
                'time' => now()->toDayDateTimeString().' UTC',
                'security_url' => route($user->preferredLocale().'.account.profile'),
            ]);
        }
    }
}
