<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (FR-140, FR-142): CSP with per-request nonces, HSTS, frame protection,
 * MIME sniffing protection, referrer and permissions policies.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if (config('platform.security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $devServer = app()->environment('local') && file_exists(public_path('hot'))
            ? ' '.trim((string) file_get_contents(public_path('hot'))).' ws://localhost:5173 ws://127.0.0.1:5173'
            : '';

        $turnstile = config('platform.captcha.driver') === 'turnstile' ? ' https://challenges.cloudflare.com' : '';
        $adminPath = trim((string) config('platform.admin.path'), '/');
        $isAdmin = $request->is($adminPath, $adminPath.'/*', 'livewire*', 'filament*');

        if ($isAdmin) {
            // Filament (Livewire + Alpine) evaluates expressions at runtime and ships inline scripts
            // without nonces, so the staff-only back-office needs 'unsafe-inline'/'unsafe-eval'.
            // Compensating controls: escaped output everywhere, 2FA-gated staff access, optional IP
            // allowlist, no third-party origins. The public site keeps the strict nonce policy.
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'{$devServer}",
                "style-src 'self' 'unsafe-inline'{$devServer}",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'{$devServer}",
                "frame-src 'self' blob:",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'{$turnstile}{$devServer}",
            "style-src 'self' 'nonce-{$nonce}'{$devServer}",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "media-src 'self' blob:",
            "connect-src 'self'{$devServer}",
            "worker-src 'self' blob:",
            "frame-src 'self'{$turnstile}",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "manifest-src 'self'",
            ...($request->isSecure() ? ['upgrade-insecure-requests'] : []),
        ]);
    }
}
