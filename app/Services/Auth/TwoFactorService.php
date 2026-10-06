<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication (FR-144). Secrets are encrypted at rest; used codes
 * cannot be replayed within their validity window.
 */
class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa, private readonly AuditLogger $audit) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(config('platform.brand.name'), $user->email, $secret);
        $options = new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false]);

        return (new QRCode($options))->render($uri);
    }

    public function verifySecret(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Verifies a code for a user with replay protection; falls back to a one-time recovery code.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($digits) === 6) {
            $timestamp = $this->google2fa->verifyKeyNewer((string) $user->two_factor_secret, $digits, (int) Cache::get('2fa:last:'.$user->id, 0), 1);
            if ($timestamp === false) {
                return false;
            }
            Cache::put('2fa:last:'.$user->id, $timestamp === true ? $this->google2fa->getTimestamp() : $timestamp, now()->addMinutes(5));

            return true;
        }

        return $this->useRecoveryCode($user, $code);
    }

    /**
     * @return array<int, string>
     */
    public function enable(User $user, string $secret): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->log('security.2fa_enabled', $user, null, null, $user);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('security.2fa_disabled', $user, null, null, $user);
    }

    /**
     * @return array<int, string>
     */
    private function newRecoveryCodes(): array
    {
        return array_map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)), range(1, 8));
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $hash = hash('sha256', Str::upper(trim($code)));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                $this->audit->log('security.recovery_code_used', $user, null, ['remaining' => count($codes)], $user);

                return true;
            }
        }

        return false;
    }
}
