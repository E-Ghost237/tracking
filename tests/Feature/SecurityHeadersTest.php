<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\TestingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Transport and browser hardening (FR-140 to FR-142), CSRF and append-only audit (FR-130).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    #[Test]
    public function public_pages_send_a_strict_nonce_based_policy(): void
    {
        $response = $this->get('/')->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    #[Test]
    public function every_inline_script_on_the_home_page_carries_the_nonce(): void
    {
        $response = $this->get('/');
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);
        preg_match_all('/<script(?![^>]*type="application\/json")([^>]*)>/', $response->getContent(), $scripts);

        foreach ($scripts[1] as $attributes) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $attributes);
        }
    }

    #[Test]
    public function state_changing_web_requests_need_a_csrf_token(): void
    {
        $this->withMiddleware();
        $this->app['env'] = 'production';

        $response = $this->call('POST', '/login', ['email' => 'a@example.test', 'password' => 'x']);
        $this->assertSame(419, $response->getStatusCode());
    }

    #[Test]
    public function reflected_input_is_escaped(): void
    {
        $this->get('/track?numbers='.urlencode('"><script>alert(1)</script>'))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/quote?from='.urlencode('"><img src=x onerror=alert(1)>'))
            ->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    #[Test]
    public function api_errors_use_one_shape_and_hide_internals(): void
    {
        $this->getJson('/api/v1/orders/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found')->assertJsonMissingPath('exception');
    }

    #[Test]
    public function the_builtin_captcha_never_exposes_its_answer_as_text(): void
    {
        config(['platform.captcha.driver' => 'builtin']);

        $challenge = $this->withHeader('Referer', 'http://localhost:8000/register')->getJson('/api/v1/captcha')->assertOk()->json();

        $this->assertStringStartsWith('data:image/png;base64,', $challenge['image']);
        $png = base64_decode(substr($challenge['image'], strlen('data:image/png;base64,')), true);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertStringNotContainsString('<text', $png);
        $this->assertArrayNotHasKey('svg', $challenge);
        $this->get('/register')->assertDontSee('<text', false)->assertDontSee('image/svg+xml;base64', false);
    }

    #[Test]
    public function oversized_requests_get_a_clean_413(): void
    {
        $this->call('POST', '/api/v1/quotes', [], [], [], ['CONTENT_LENGTH' => 64 * 1024 * 1024, 'HTTP_ACCEPT' => 'application/json'])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'payload_too_large')
            ->assertJsonMissingPath('exception');
    }

    #[Test]
    public function the_audit_log_is_append_only(): void
    {
        $entry = app(AuditLogger::class)->log('test.entry', User::factory()->create());

        $this->expectException(QueryException::class);
        AuditLog::query()->whereKey($entry->id)->toBase()->update(['action' => 'tampered']);
    }

    #[Test]
    public function secrets_never_appear_in_audit_entries(): void
    {
        $user = User::factory()->create();
        app(AuditLogger::class)->log('test.secret', $user, ['password' => 'hunter2'], ['two_factor_secret' => 'ABC']);

        $raw = (string) json_encode(AuditLog::query()->latest('id')->first()->only(['before', 'after']));
        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertStringNotContainsString('ABC', $raw);
    }
}
