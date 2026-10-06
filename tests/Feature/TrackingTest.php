<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Shipment;
use App\Models\TrackingSubscription;
use App\Models\User;
use App\Services\Payments\ProofReviewService;
use App\Services\Shipping\ShipmentEventService;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BooksShipments;
use Tests\TestCase;

/**
 * Public tracking (FR-10 to FR-18) and tracking events (BR-08, BR-10, R6).
 */
class TrackingTest extends TestCase
{
    use BooksShipments, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Mail::fake();
        Cache::flush();
    }

    #[Test]
    public function a_released_shipment_shows_public_fields_only(): void
    {
        $shipment = $this->releasedShipment();

        $response = $this->getJson('/api/v1/track?numbers='.$shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('results.0.found', true)
            ->assertJsonPath('results.0.origin', 'Douala, CM')
            ->assertJsonPath('results.0.status', 'ready')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $body = $response->getContent();
        foreach (['Rue Joss', 'rue de Rivoli', '+33 6 00', 'Jean Dupont', 'Chantal', $shipment->user->email] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
    }

    #[Test]
    public function numbers_are_normalised_and_up_to_twenty_are_accepted(): void
    {
        $shipment = $this->releasedShipment();
        $spaced = strtolower(str_replace('-', ' - ', $shipment->tracking_number));

        $this->getJson('/api/v1/track?numbers='.urlencode($spaced))->assertOk()->assertJsonPath('results.0.found', true);

        $many = implode(',', array_map(fn ($i) => 'UNKNOWN'.$i, range(1, 30)));
        $this->assertCount(20, $this->getJson('/api/v1/track?numbers='.$many)->json('results'));
    }

    #[Test]
    public function unknown_numbers_return_a_clear_message_never_an_invented_result(): void
    {
        $this->getJson('/api/v1/track?numbers=CV-AIR-999999')
            ->assertOk()->assertJsonPath('results.0.found', false)->assertJsonMissingPath('results.0.events');

        $this->getJson('/api/v1/track?numbers=1Z999AA10123456784')
            ->assertOk()->assertJsonPath('results.0.found', false)->assertJsonPath('results.0.carrier.code', 'ups')
            ->assertJsonPath('results.0.external_url', 'https://www.ups.com/track?tracknum=1Z999AA10123456784');
    }

    #[Test]
    public function an_unpaid_shipment_cannot_be_found_publicly(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->bookOrder($customer);

        $this->assertNull($order->shipment->tracking_number);
        $this->getJson('/api/v1/track?numbers='.$order->payment_reference)->assertJsonPath('results.0.found', false);
    }

    #[Test]
    public function captcha_is_required_after_ten_failed_lookups(): void
    {
        config(['platform.captcha.driver' => 'builtin']);
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/track?numbers=NOPE'.$i)->assertOk();
        }

        $this->getJson('/api/v1/track?numbers=NOPE99')->assertStatus(429)->assertJsonPath('error.code', 'captcha_required');
    }

    #[Test]
    public function lookups_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/track?numbers=X'.$i);
        }

        $this->getJson('/api/v1/track?numbers=X31')->assertStatus(429);
    }

    #[Test]
    public function shareable_page_and_qr_code_work(): void
    {
        $shipment = $this->releasedShipment();

        $this->get(route('en.track', ['number' => $shipment->tracking_number]))->assertOk()->assertSee($shipment->tracking_number)
            ->assertSee('noindex', false);
        $this->get("/api/v1/track/{$shipment->tracking_number}/qr")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    #[Test]
    public function the_latest_public_event_drives_status_and_delivered_is_protected(): void
    {
        $shipment = $this->releasedShipment();
        $agent = User::factory()->staff('support_agent')->create();
        $admin = User::factory()->staff('admin')->create();
        $events = app(ShipmentEventService::class);

        $events->add($shipment, ['status' => 'in_transit', 'label' => 'Departed', 'place' => 'Douala, CM', 'occurred_at' => now()], $agent);
        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);

        $events->add($shipment, ['status' => 'delayed', 'label' => 'Internal note', 'occurred_at' => now()->addMinute(), 'is_public' => false], $agent);
        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);

        $events->add($shipment, ['status' => 'delivered', 'label' => 'Delivered', 'occurred_at' => now()->addMinutes(2)], $agent);
        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);

        $this->assertDomainError(fn () => $events->add($shipment, ['status' => 'returned', 'label' => 'x', 'occurred_at' => now()], $agent), 'after_delivery');
        $this->assertDomainError(fn () => $events->add($shipment, ['status' => 'delivered', 'label' => 'x', 'occurred_at' => now()], $admin), 'already_delivered');
        $events->add($shipment, ['status' => 'returned', 'label' => 'Returned', 'occurred_at' => now()->addMinutes(3)], $admin);

        $public = $this->getJson('/api/v1/track?numbers='.$shipment->tracking_number)->json('results.0.events');
        $this->assertNotContains('Internal note', array_column($public, 'label'));
        $this->assertSame('Corvane', $public[0]['source']);
    }

    #[Test]
    public function subscriptions_need_double_opt_in(): void
    {
        $shipment = $this->releasedShipment();

        $this->postJson('/api/v1/tracking-subscriptions', ['number' => $shipment->tracking_number, 'email' => 'fan@example.test'])->assertAccepted();
        $subscription = TrackingSubscription::query()->firstOrFail();
        $this->assertNull($subscription->verified_at);
        $this->get('/tracking/confirm/'.str_repeat('a', 48))->assertRedirect();
        $this->assertNull($subscription->fresh()->verified_at);
    }

    private function releasedShipment(): Shipment
    {
        $customer = User::factory()->customer()->create();
        $this->enabledMethod();
        $order = $this->bookOrder($customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();
        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order), ['Accept' => 'application/json'])->assertCreated();
        app(ProofReviewService::class)->approve($order->proofs()->firstOrFail(), User::factory()->staff('payment_verifier')->create(), [
            'amount_matches' => true, 'reference_present' => true, 'date_after_order' => true, 'payer_plausible' => true, 'not_duplicate' => true,
        ]);
        $this->app['auth']->forgetGuards();
        Cache::flush();

        return $order->shipment()->firstOrFail();
    }

    private function assertDomainError(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail("Expected domain error $code.");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }
}
