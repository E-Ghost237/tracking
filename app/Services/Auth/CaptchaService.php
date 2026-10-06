<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * CAPTCHA for login, registration, password reset, contact and repeated failed tracking (FR-147, FR-18).
 * Production uses Cloudflare Turnstile; the built-in arithmetic challenge needs no third party.
 */
class CaptchaService
{
    private const SESSION_KEY = 'captcha.challenges';

    public function driver(): string
    {
        return (string) config('platform.captcha.driver', 'builtin');
    }

    public function enabled(): bool
    {
        return $this->driver() !== 'none';
    }

    /**
     * @return array{driver: string, id?: string, svg?: string, site_key?: ?string}
     */
    public function challenge(Request $request): array
    {
        if ($this->driver() === 'turnstile') {
            return ['driver' => 'turnstile', 'site_key' => config('platform.captcha.turnstile_site_key')];
        }

        $a = random_int(2, 19);
        $b = random_int(2, 9);
        $id = Str::random(24);

        $challenges = array_slice((array) $request->session()->get(self::SESSION_KEY, []), -4, 4, true);
        $challenges[$id] = ['answer' => hash('sha256', (string) ($a + $b)), 'expires' => now()->addMinutes(10)->timestamp];
        $request->session()->put(self::SESSION_KEY, $challenges);

        return ['driver' => 'builtin', 'id' => $id, 'svg' => $this->render($a.' + '.$b.' = ?')];
    }

    public function verify(Request $request, ?string $id, ?string $answer): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if ($this->driver() === 'turnstile') {
            return $this->verifyTurnstile($request, (string) $answer);
        }

        $challenges = (array) $request->session()->get(self::SESSION_KEY, []);
        $challenge = $challenges[(string) $id] ?? null;
        // Single use: a challenge is removed whether or not the answer is right.
        unset($challenges[(string) $id]);
        $request->session()->put(self::SESSION_KEY, $challenges);

        if (! is_array($challenge) || $challenge['expires'] < now()->timestamp) {
            return false;
        }

        return hash_equals($challenge['answer'], hash('sha256', trim((string) $answer)));
    }

    private function verifyTurnstile(Request $request, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('platform.captcha.turnstile_secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            return (bool) $response->json('success', false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Renders the arithmetic question as a noisy SVG so it is not readable as page text.
     */
    private function render(string $text): string
    {
        $width = 170;
        $height = 54;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img">';
        $svg .= '<rect width="100%" height="100%" rx="8" fill="#eef2f7"/>';

        for ($i = 0; $i < 7; $i++) {
            $svg .= sprintf('<path d="M%d %d Q %d %d %d %d" stroke="#%s" stroke-width="1.4" fill="none" opacity="0.6"/>',
                random_int(0, 40), random_int(0, $height), random_int(40, 130), random_int(0, $height), random_int(130, $width), random_int(0, $height),
                substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 6));
        }

        $x = 16;
        foreach (str_split($text) as $char) {
            if ($char === ' ') {
                $x += 8;

                continue;
            }
            $svg .= sprintf('<text x="%d" y="%d" font-family="monospace" font-size="%d" font-weight="700" fill="#0b1b33" transform="rotate(%d %d %d)">%s</text>',
                $x, random_int(32, 40), random_int(22, 27), random_int(-18, 18), $x, 34, htmlspecialchars($char, ENT_XML1));
            $x += random_int(15, 19);
        }

        return $svg.'</svg>';
    }
}
