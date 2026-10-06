<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Authentication rules (section 2.1, FR-143, FR-144, FR-147).
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    #[Test]
    public function an_account_locks_for_15_minutes_after_five_failures(): void
    {
        $user = User::factory()->customer()->create(['password' => 'Correct-Horse-9']);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong']);
            $this->app['cache']->store()->flush();
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(16)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x']);
        }

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x'])->assertStatus(429);
    }

    #[Test]
    public function unknown_and_known_emails_get_the_same_login_and_reset_responses(): void
    {
        $user = User::factory()->customer()->create();

        $message = ['email' => __('These credentials do not match our records.')];
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'bad'])->assertRedirect('/login')->assertSessionHasErrors($message);
        $this->from('/login')->post('/login', ['email' => 'ghost@example.test', 'password' => 'bad'])->assertRedirect('/login')->assertSessionHasErrors($message);

        $a = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email, 'captcha_answer' => 'x']);
        $b = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ghost@example.test', 'captcha_answer' => 'x']);
        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));
    }

    #[Test]
    public function registering_an_existing_email_looks_identical_and_warns_the_owner(): void
    {
        $user = User::factory()->customer()->create();
        $payload = ['name' => 'Someone', 'password' => 'Very-Strong-Pass-42', 'password_confirmation' => 'Very-Strong-Pass-42', 'terms' => '1', 'captcha_answer' => 'x'];

        $new = $this->post('/register', $payload + ['email' => 'fresh@example.test']);
        $dup = $this->post('/register', $payload + ['email' => strtoupper($user->email)]);

        $new->assertRedirect('/login');
        $dup->assertRedirect('/login');
        $this->assertSame(1, User::query()->where('email', $user->email)->count());
        $this->assertTrue(NotificationLog::query()->where('event', 'account.register_existing')->where('recipient', $user->email)->exists());
        $this->assertTrue(User::query()->where('email', 'fresh@example.test')->first()->hasRole('customer'));
    }

    #[Test]
    public function weak_passwords_are_refused(): void
    {
        $this->post('/register', ['name' => 'A B', 'email' => 'weak@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1', 'captcha_answer' => 'x'])
            ->assertSessionHasErrors('password');
    }

    #[Test]
    public function two_factor_login_requires_a_valid_unreplayed_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user = User::factory()->staff('admin', $secret)->create(['password' => 'Correct-Horse-9']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])->assertRedirect('/two-factor');
        $this->assertGuest();

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/two-factor', ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/en/logout');
        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9']);
        $this->post('/two-factor', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    #[Test]
    public function the_two_factor_step_cannot_be_skipped(): void
    {
        $user = User::factory()->staff('admin')->create(['password' => 'Correct-Horse-9']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9']);

        $this->get('/account')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect();
        $this->assertGuest();
    }

    #[Test]
    public function the_session_id_changes_on_login(): void
    {
        $user = User::factory()->customer()->create(['password' => 'Correct-Horse-9']);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9']);

        $this->assertNotSame($before, session()->getId());
    }

    #[Test]
    public function a_disabled_account_is_signed_out(): void
    {
        $user = User::factory()->customer()->create();
        $user->forceFill(['status' => 'disabled'])->save();

        $this->actingAs($user)->get('/account')->assertRedirect('/login');
        $this->assertGuest();
    }
}
