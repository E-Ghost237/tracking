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
     * @return array{driver: string, id?: string, image?: string, site_key?: ?string}
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

        return ['driver' => 'builtin', 'id' => $id, 'image' => $this->render($a.' + '.$b.' = ?')];
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
     * Rasterises the question to a PNG: glyphs are drawn as pixels with random rotation,
     * offsets, colour, a wave distortion and noise, so the answer never appears as text
     * in the response. This is defence in depth behind the rate limits; production uses Turnstile.
     */
    private function render(string $text): string
    {
        $width = 190;
        $height = 60;
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 238, 242, 247));

        for ($i = 0; $i < 220; $i++) {
            imagesetpixel($canvas, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($canvas, random_int(120, 200), random_int(120, 200), random_int(120, 200)));
        }

        $x = 12;
        foreach (mb_str_split($text) as $char) {
            if ($char === ' ') {
                $x += 7;

                continue;
            }
            $color = imagecolorallocate($canvas, random_int(10, 70), random_int(20, 60), random_int(40, 90));
            imagettftext($canvas, random_int(19, 24), random_int(-24, 24), $x, random_int(38, 46), $color, $font, $char);
            $x += random_int(15, 19);
        }

        for ($i = 0; $i < 5; $i++) {
            imagesetthickness($canvas, random_int(1, 2));
            imageline($canvas, random_int(0, 40), random_int(0, $height), random_int($width - 40, $width), random_int(0, $height), imagecolorallocate($canvas, random_int(60, 160), random_int(60, 160), random_int(60, 160)));
        }

        // Sine-wave displacement breaks straight baselines that OCR relies on.
        $warped = imagecreatetruecolor($width, $height);
        imagefilledrectangle($warped, 0, 0, $width, $height, imagecolorallocate($warped, 238, 242, 247));
        $amplitude = random_int(2, 4);
        $period = random_int(18, 30);
        $phase = random_int(0, 100) / 10;
        for ($column = 0; $column < $width; $column++) {
            $shift = (int) round($amplitude * sin($column / $period + $phase));
            imagecopy($warped, $canvas, $column, max(0, $shift), $column, max(0, -$shift), 1, $height - abs($shift));
        }

        ob_start();
        imagepng($warped);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($warped);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
