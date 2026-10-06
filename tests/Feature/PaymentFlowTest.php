<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodField;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Payments\OrderExpiryService;
use App\Services\Payments\ProofReviewService;
use App\Services\Settings;
use Database\Seeders\TestingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BooksShipments;
use Tests\TestCase;

/**
 * Payment acceptance scenarios P1 to P13 (spec section 13.2).
 */
class PaymentFlowTest extends TestCase
{
    use BooksShipments, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Mail::fake();
        $this->customer = User::factory()->customer()->create(['created_at' => now()->subDays(90)]);
    }

    #[Test]
    public function p1_pay_page_and_method_list_never_contain_account_details(): void
    {
        $this->enabledMethod('zelle', 'USD', ['secret-zelle@example.test', 'Hidden Holder LLC']);
        $order = $this->bookOrder($this->customer);

        $this->useWebGuard();
        $page = $this->get(route('en.account.orders.pay', $order))->assertOk();
        $page->assertSee('Zelle')->assertDontSee('secret-zelle@example.test')->assertDontSee('Hidden Holder LLC');

        $list = $this->getJson("/api/v1/orders/{$order->public_id}/payment-methods")->assertOk();
        $this->assertStringNotContainsString('secret-zelle', $list->getContent());

        $this->useWebGuard();
        $this->get('/sitemap.xml')->assertDontSee('secret-zelle');
        $this->getJson("/api/v1/orders/{$order->public_id}/payment-method")->assertOk()->assertJsonPath('data', null);
    }

    #[Test]
    public function p2_selecting_a_method_returns_details_reference_amount_and_expiry(): void
    {
        $this->enabledMethod('zelle', 'USD', ['pay@corvane.example', 'Corvane LLC']);
        $order = $this->bookOrder($this->customer);

        $response = $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonPath('reference', $order->payment_reference)
            ->assertJsonPath('fields.0.value', 'pay@corvane.example')
            ->assertJsonPath('amount_due.amount', $order->total);

        $this->assertNotNull($response->json('expires_at'));
        $this->assertSame(OrderStatus::MethodSelected, $order->fresh()->status);
        $this->assertSame(1, $order->payments()->count());
    }

    #[Test]
    public function p3_another_user_cannot_select_a_method_on_someone_elses_order(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $intruder = User::factory()->customer()->create();

        $this->actingAs($intruder)
            ->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])
            ->assertNotFound()
            ->assertDontSee('payments@example.test');

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$order->public_id}/payment-method")->assertNotFound();
        $this->useWebGuard();
        $this->actingAs($intruder)->get(route('en.account.orders.pay', $order))->assertNotFound();
        $this->assertSame(0, $order->payments()->count());
    }

    #[Test]
    public function guests_get_401_on_the_selection_endpoint(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertUnauthorized();
    }

    #[Test]
    public function p4_switching_to_iban_shows_iban_and_no_longer_returns_zelle_details(): void
    {
        $this->enabledMethod('zelle', 'USD', ['zelle-handle@example.test']);
        $this->enabledMethod('iban', 'EUR', ['FR76 1111 2222 3333 4444 5555 666']);
        $order = $this->bookOrder($this->customer);

        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();
        $iban = $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'iban'])->assertOk();

        $iban->assertJsonPath('fields.0.value', 'FR76 1111 2222 3333 4444 5555 666')->assertJsonPath('amount_due.currency', 'EUR');
        $this->assertNotNull($iban->json('exchange_rate'));

        $current = $this->getJson("/api/v1/orders/{$order->public_id}/payment-method")->assertOk();
        $this->assertStringNotContainsString('zelle-handle', $current->getContent());
        $this->assertStringContainsString('FR76', $current->getContent());
    }

    #[Test]
    public function p5_a_valid_png_proof_puts_the_order_under_review(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();

        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('review_minutes', 30);

        $order->refresh();
        $this->assertSame(OrderStatus::UnderReview, $order->status);
        $proof = $order->proofs()->firstOrFail();
        $this->assertSame(ProofStatus::UnderReview, $proof->status);
        $this->assertSame('clean', $proof->files()->first()->scan_status);
        $this->assertSame(ShipmentStatus::PaymentUnderReview, $order->shipment->status);
        $this->assertNull($order->shipment->tracking_number);
        $this->assertTrue(NotificationLog::query()->where('event', 'proof.received')->exists());
    }

    #[Test]
    public function p6_an_executable_renamed_to_jpg_is_rejected(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();

        $exe = UploadedFile::fake()->createWithContent('receipt.jpg', "MZ\x90\x00".str_repeat("\x00", 200).'This program cannot be run in DOS mode');
        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order, $exe), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $script = UploadedFile::fake()->createWithContent('receipt.jpg', '<?php system($_GET["c"]); ?>');
        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order, $script), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame(0, PaymentProof::query()->count());
        $this->assertSame(OrderStatus::MethodSelected, $order->fresh()->status);
    }

    #[Test]
    public function an_image_with_huge_declared_dimensions_is_refused_before_decoding(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();

        $ihdr = 'IHDR'.pack('NNCCCCC', 9000, 9000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
        $bomb = UploadedFile::fake()->createWithContent('proof.png', $png);

        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order, $bomb), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('error.code', 'file_type_not_allowed');
    }

    #[Test]
    public function a_real_image_carrying_an_embedded_script_is_quarantined_by_the_scan(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();

        $png = $this->pngProof();
        $polyglot = UploadedFile::fake()->createWithContent('proof.png', file_get_contents($png->getRealPath()).'<script>alert(1)</script>');

        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order, $polyglot), ['Accept' => 'application/json'])
            ->assertCreated();

        $order->refresh();
        $this->assertSame(OrderStatus::ProofRejected, $order->status);
        $this->assertSame('infected', $order->proofs()->first()->files()->first()->scan_status);
    }

    #[Test]
    public function p7_the_same_image_on_a_second_order_is_flagged_as_duplicate(): void
    {
        $this->enabledMethod();
        $first = $this->bookOrder($this->customer);
        $second = $this->bookOrder($this->customer);
        $image = $this->pngProof('same.png', 'fixed-seed');
        $bytes = file_get_contents($image->getRealPath());

        foreach ([$first, $second] as $order) {
            $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();
            $payload = $this->proofPayload($order, UploadedFile::fake()->createWithContent('same.png', $bytes));
            $this->post("/api/v1/orders/{$order->public_id}/proofs", $payload, ['Accept' => 'application/json'])->assertCreated();
        }

        $this->assertFalse($first->proofs()->first()->is_duplicate);
        $duplicate = $second->proofs()->first();
        $this->assertTrue($duplicate->is_duplicate);
        $this->assertContains($first->number, $duplicate->duplicate_of);
    }

    #[Test]
    public function p8_approval_releases_tracking_number_label_email_and_audit_entry(): void
    {
        $verifier = User::factory()->staff('payment_verifier')->create();
        $proof = $this->submittedProof();
        $order = $proof->order;

        $result = app(ProofReviewService::class)->approve($proof, $verifier, $this->fullChecklist());

        $this->assertSame(ProofReviewService::RESULT_RELEASED, $result);
        $order->refresh();
        $shipment = $order->shipment;
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->receipt_number);
        $this->assertMatchesRegularExpression('/^CV-AIR-\d{6}$/', $shipment->tracking_number);
        $this->assertSame(ShipmentStatus::Ready, $shipment->status);
        $this->assertTrue($shipment->documents()->where('type', 'label')->whereNotNull('file_id')->exists());
        $this->assertSame(1, $order->invoices()->count());
        $this->assertTrue(NotificationLog::query()->where('event', 'proof.approved')->where('recipient', $this->customer->email)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'payment.review.approve')->where('user_id', $verifier->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'order.released')->exists());

        $this->actingAs($this->customer)->getJson("/api/v1/shipments/{$shipment->public_id}")
            ->assertOk()->assertJsonPath('data.tracking_number', $shipment->tracking_number);
    }

    #[Test]
    public function p9_rejection_emails_the_reason_and_allows_a_new_upload(): void
    {
        $verifier = User::factory()->staff('payment_verifier')->create();
        $proof = $this->submittedProof();
        $order = $proof->order;

        app(ProofReviewService::class)->reject($proof, $verifier, 'amount_mismatch', 'You sent 10 USD less.');

        $this->assertSame(OrderStatus::ProofRejected, $order->fresh()->status);
        $this->assertTrue(NotificationLog::query()->where('event', 'proof.rejected')->exists());

        $this->actingAs($this->customer)
            ->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order), ['Accept' => 'application/json'])
            ->assertCreated();
        $this->assertSame(OrderStatus::UnderReview, $order->fresh()->status);
    }

    #[Test]
    public function p10_orders_above_the_threshold_need_two_different_approvers(): void
    {
        app(Settings::class)->set('two_person_threshold', 1000);
        $first = User::factory()->staff('payment_verifier')->create();
        $second = User::factory()->staff('payment_verifier')->create();
        $proof = $this->submittedProof();
        $reviews = app(ProofReviewService::class);

        $this->assertSame(ProofReviewService::RESULT_FIRST_APPROVAL, $reviews->approve($proof, $first, $this->fullChecklist()));
        $this->assertSame(OrderStatus::UnderReview, $proof->order->fresh()->status);

        try {
            $reviews->approve($proof->fresh(), $first, $this->fullChecklist());
            $this->fail('The same verifier approved twice.');
        } catch (DomainRuleException $e) {
            $this->assertSame('second_approver_required', $e->errorCode);
        }

        $this->assertSame(ProofReviewService::RESULT_RELEASED, $reviews->approve($proof->fresh(), $second, $this->fullChecklist()));
        $this->assertSame(OrderStatus::Paid, $proof->order->fresh()->status);
    }

    #[Test]
    public function a_verifier_cannot_approve_a_payment_on_their_own_order(): void
    {
        $verifier = User::factory()->staff('payment_verifier')->create(['created_at' => now()->subDays(90)]);
        $this->customer = $verifier;
        $proof = $this->submittedProof();

        try {
            app(ProofReviewService::class)->approve($proof, $verifier, $this->fullChecklist());
            $this->fail('A verifier approved their own order.');
        } catch (DomainRuleException $e) {
            $this->assertSame('conflict_of_interest', $e->errorCode);
        }

        $this->assertSame(OrderStatus::UnderReview, $proof->order->fresh()->status);
    }

    #[Test]
    public function support_agents_cannot_review_payments(): void
    {
        $agent = User::factory()->staff('support_agent')->create();
        $proof = $this->submittedProof();

        $this->expectException(DomainRuleException::class);
        app(ProofReviewService::class)->approve($proof, $agent, $this->fullChecklist());
    }

    #[Test]
    public function a_partial_payment_records_the_balance_due(): void
    {
        $verifier = User::factory()->staff('payment_verifier')->create();
        $proof = $this->submittedProof();
        $order = $proof->order;

        $result = app(ProofReviewService::class)->approve($proof, $verifier, $this->fullChecklist(), null, (int) floor($order->total / 2));

        $this->assertSame(ProofReviewService::RESULT_PARTIAL, $result);
        $order->refresh();
        $this->assertSame(OrderStatus::PartiallyPaid, $order->status);
        $this->assertGreaterThan(0, $order->balanceDue());
        $this->assertNull($order->shipment->tracking_number);
    }

    #[Test]
    public function p11_a_disabled_method_disappears_for_new_orders_but_history_remains(): void
    {
        $method = $this->enabledMethod();
        $proof = $this->submittedProof();

        $method->forceFill(['is_enabled' => false])->save();
        $order = $this->bookOrder($this->customer);

        $this->getJson("/api/v1/orders/{$order->public_id}/payment-methods")->assertOk()->assertJsonMissing(['id' => 'zelle']);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertStatus(422);
        $this->assertSame('Zelle', $proof->fresh()->orderPayment->method->name);
    }

    #[Test]
    public function p12_an_expired_order_returns_no_details_and_emails_the_customer(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();

        $this->travel(49)->hours();
        app(OrderExpiryService::class)->run();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertTrue(NotificationLog::query()->where('event', 'order.expired')->exists());
        $this->getJson("/api/v1/orders/{$order->public_id}/payment-method")->assertOk()->assertJsonPath('data', null);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertStatus(409);
    }

    #[Test]
    public function reminders_go_out_once_per_window_before_expiry(): void
    {
        $order = $this->bookOrder($this->customer);

        $this->travel(25)->hours();
        app(OrderExpiryService::class)->sendReminders();
        app(OrderExpiryService::class)->sendReminders();
        $this->assertSame(1, NotificationLog::query()->where('event', 'payment.reminder')->count());

        $this->travel(22)->hours();
        app(OrderExpiryService::class)->sendReminders();
        $this->assertSame(2, NotificationLog::query()->where('event', 'payment.reminder')->count());
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    #[Test]
    public function p13_changing_an_iban_is_audited_alerts_admins_and_keeps_unpaid_snapshots(): void
    {
        User::factory()->staff('admin')->create(['email' => 'boss@example.test']);
        $method = $this->enabledMethod('iban', 'EUR', ['FR76 OLD OLD OLD']);
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'iban'])->assertOk();

        $field = PaymentMethodField::query()->where('payment_method_id', $method->id)->firstOrFail();
        $field->update(['value' => 'FR76 NEW NEW NEW']);

        $this->assertTrue(AuditLog::query()->where('action', 'payment_method.field_changed')->exists());
        $this->assertTrue(NotificationLog::query()->where('event', 'admin.payment_method_changed')->where('recipient', 'boss@example.test')->exists());
        $this->assertStringNotContainsString('FR76 NEW', (string) json_encode(AuditLog::query()->pluck('after')));

        $this->getJson("/api/v1/orders/{$order->public_id}/payment-method")->assertOk()->assertJsonPath('data.fields.0.value', 'FR76 OLD OLD OLD');
    }

    #[Test]
    public function a_delayed_detail_change_only_goes_live_after_the_delay(): void
    {
        app(Settings::class)->set('payment_details_change_delay_hours', 24);
        $method = $this->enabledMethod('zelle', 'USD', ['old@example.test']);
        $field = PaymentMethodField::query()->where('payment_method_id', $method->id)->firstOrFail();

        $field->update(['value' => 'attacker@example.test']);
        $this->assertSame('old@example.test', $field->fresh()->value);
        $this->assertSame('attacker@example.test', $field->fresh()->pending_value);

        $this->travel(25)->hours();
        $this->artisan('payments:promote-details')->assertSuccessful();
        $this->assertSame('attacker@example.test', $field->fresh()->value);
    }

    #[Test]
    public function payment_method_values_and_review_records_are_protected_at_rest(): void
    {
        $method = $this->enabledMethod('zelle', 'USD', ['plaintext-handle@example.test']);
        $raw = \DB::table('payment_method_fields')->where('payment_method_id', $method->id)->value('value');
        $this->assertStringNotContainsString('plaintext-handle', $raw);

        $verifier = User::factory()->staff('payment_verifier')->create();
        $proof = $this->submittedProof();
        app(ProofReviewService::class)->reject($proof, $verifier, 'unreadable');

        $this->expectException(QueryException::class);
        \DB::table('payment_reviews')->update(['decision' => 'approve']);
    }

    #[Test]
    public function gift_cards_require_an_old_verified_account_and_are_encrypted(): void
    {
        $method = $this->enabledMethod('gift-card', 'USD', ['Use the form below']);
        $method->forceFill(['gift_card_rules' => ['brands' => ['Apple'], 'per_card_min' => 100, 'per_card_max' => 100000000, 'daily_limit' => 0, 'min_account_age_days' => 30]])->save();

        $young = User::factory()->customer()->create(['created_at' => now()->subDays(2)]);
        $youngOrder = $this->bookOrder($young);
        $this->postJson("/api/v1/orders/{$youngOrder->public_id}/payment-method", ['method_id' => 'gift-card'])->assertStatus(422);

        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'gift-card'])->assertOk();
        $payload = $this->proofPayload($order) + ['gift_card' => ['brand' => 'Apple', 'code' => 'ABCD-EFGH-IJKL-9876', 'amount' => 50]];
        $this->post("/api/v1/orders/{$order->public_id}/proofs", $payload, ['Accept' => 'application/json'])->assertCreated();

        $raw = \DB::table('gift_card_submissions')->first();
        $this->assertSame('9876', $raw->code_last4);
        $this->assertStringNotContainsString('ABCD', $raw->code);
    }

    #[Test]
    public function the_idempotency_key_prevents_double_proof_submission(): void
    {
        $this->enabledMethod();
        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();
        $payload = $this->proofPayload($order);

        $first = $this->post("/api/v1/orders/{$order->public_id}/proofs", $payload, ['Accept' => 'application/json', 'Idempotency-Key' => 'abc123def456']);
        $second = $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order), ['Accept' => 'application/json', 'Idempotency-Key' => 'abc123def456']);

        $first->assertCreated();
        $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, PaymentProof::query()->count());
    }

    private function submittedProof(): PaymentProof
    {
        if (! PaymentMethod::query()->where('slug', 'zelle')->where('is_enabled', true)->exists()) {
            $this->enabledMethod();
        }

        $order = $this->bookOrder($this->customer);
        $this->postJson("/api/v1/orders/{$order->public_id}/payment-method", ['method_id' => 'zelle'])->assertOk();
        $this->post("/api/v1/orders/{$order->public_id}/proofs", $this->proofPayload($order), ['Accept' => 'application/json'])->assertCreated();

        return PaymentProof::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, bool>
     */
    private function fullChecklist(): array
    {
        return ['amount_matches' => true, 'reference_present' => true, 'date_after_order' => true, 'payer_plausible' => true, 'not_duplicate' => true];
    }
}
