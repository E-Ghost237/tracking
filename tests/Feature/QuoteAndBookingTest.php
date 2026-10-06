<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Quote;
use App\Models\RateCard;
use App\Models\User;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BooksShipments;
use Tests\TestCase;

/**
 * Quote engine (FR-20 to FR-25) and booking wizard (FR-30 to FR-32, BR-02).
 */
class QuoteAndBookingTest extends TestCase
{
    use BooksShipments, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    #[Test]
    public function a_quote_uses_the_larger_of_actual_and_volumetric_weight(): void
    {
        $response = $this->postJson('/api/v1/quotes', $this->quote(weight: 2, dims: [50, 40, 40]))
            ->assertCreated()
            ->assertJsonPath('data.chargeable_weight_kg', 16)
            ->assertJsonPath('data.volumetric_weight_kg', 16)
            ->assertJsonPath('data.network', 'europe');

        $this->assertMatchesRegularExpression('/^Q-[A-Z0-9]{6}$/', $response->json('data.reference'));
        $quote = Quote::query()->firstOrFail();
        $this->assertTrue($quote->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
    }

    #[Test]
    public function the_price_follows_the_rate_card_formula(): void
    {
        $data = $this->postJson('/api/v1/quotes', $this->quote(weight: 10, dims: [10, 10, 10]))->assertCreated()->json('data');
        $line = RateCard::current()->lines()->where('zone_from', 'CAF')->where('zone_to', 'EU')->where('mode', 'air')
            ->where('weight_from_kg', '<=', 10)->where('weight_to_kg', '>=', 10)->firstOrFail();

        $freight = (int) round($line->base_fee + $line->price_per_kg * 10);
        $fuel = (int) round($freight * 0.12);
        $this->assertSame($freight + $fuel + 500, $data['total']);
    }

    #[Test]
    public function road_freight_is_refused_between_continents_with_a_suggestion(): void
    {
        $this->postJson('/api/v1/quotes', $this->quote(mode: 'road'))
            ->assertStatus(422)->assertJsonPath('error.code', 'road_unavailable')->assertJsonPath('error.context.suggest', ['air', 'sea']);
    }

    #[Test]
    public function quote_input_is_bounded(): void
    {
        $this->postJson('/api/v1/quotes', $this->quote(weight: 99999))->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $bad = $this->quote();
        $bad['origin']['country'] = 'XX';
        $this->postJson('/api/v1/quotes', $bad)->assertStatus(422);
        $bad = $this->quote();
        $bad['packages'] = array_fill(0, 21, $bad['packages'][0]);
        $this->postJson('/api/v1/quotes', $bad)->assertStatus(422);
    }

    #[Test]
    public function booking_creates_an_order_awaiting_payment_without_a_tracking_number(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->bookOrder($customer);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertMatchesRegularExpression('/^PAY-[A-Z0-9]{6}$/', $order->payment_reference);
        $this->assertNull($order->shipment->tracking_number);
        $this->assertSame(ShipmentStatus::AwaitingPayment, $order->shipment->status);
        $this->assertTrue($order->expires_at->between(now()->addHours(47), now()->addHours(49)));
        $this->assertNotNull($order->quote->booked_at);
    }

    #[Test]
    public function prohibited_categories_are_blocked(): void
    {
        $this->actingAs(User::factory()->customer()->create());
        $draft = $this->postJson('/api/v1/shipment-drafts')->json('id');

        $this->putJson("/api/v1/shipment-drafts/$draft", ['step' => 'packages', 'data' => ['packages' => [[
            'description' => 'Cash', 'weight_kg' => 1, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10, 'value' => 10, 'category' => 'cash',
        ]]]])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    #[Test]
    public function unverified_customers_cannot_book(): void
    {
        $this->actingAs(User::factory()->customer()->unverified()->create());

        $this->postJson('/api/v1/shipment-drafts')->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
    }

    #[Test]
    public function the_booked_price_ignores_later_rate_changes(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->bookOrder($customer);
        $total = $order->total;

        RateCard::current()->lines()->update(['price_per_kg' => 1]);

        $this->assertSame($total, $order->fresh()->total);
    }

    /**
     * @param  array<int, int>  $dims
     * @return array<string, mixed>
     */
    private function quote(float $weight = 5, array $dims = [30, 20, 15], string $mode = 'air'): array
    {
        return [
            'origin' => ['city' => 'Douala', 'country' => 'CM', 'lat' => 4.0511, 'lon' => 9.7679],
            'destination' => ['city' => 'Paris', 'country' => 'FR', 'lat' => 48.8566, 'lon' => 2.3522],
            'packages' => [['weight_kg' => $weight, 'length_cm' => $dims[0], 'width_cm' => $dims[1], 'height_cm' => $dims[2]]],
            'mode' => $mode,
            'insurance' => false,
        ];
    }
}
