<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\ShipmentDraft;
use App\Models\User;
use App\Services\Files\FileStorageService;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BooksShipments;
use Tests\TestCase;

/**
 * Object-level and role-level authorization (section 2: enforced on the server).
 */
class AuthorizationTest extends TestCase
{
    use BooksShipments, RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Mail::fake();
    }

    #[Test]
    public function customers_cannot_reach_each_others_records(): void
    {
        $owner = User::factory()->customer()->create();
        $order = $this->bookOrder($owner);
        $address = Address::query()->forceCreate(['public_id' => (string) str()->ulid(), 'user_id' => $owner->id, 'name' => 'Owner', 'line1' => 'x', 'city' => 'Paris', 'country' => 'FR']);
        $draft = ShipmentDraft::query()->create(['user_id' => $owner->id, 'step' => 1, 'data' => []]);
        $intruder = User::factory()->customer()->create();
        $this->actingAs($intruder);

        foreach ([
            "/api/v1/orders/{$order->public_id}",
            "/api/v1/orders/{$order->public_id}/proofs",
            "/api/v1/orders/{$order->public_id}/payment-methods",
            "/api/v1/shipments/{$order->shipment->public_id}",
            "/api/v1/shipment-drafts/{$draft->public_id}",
            "/api/v1/orders/{$order->public_id}/invoice",
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }

        $this->putJson("/api/v1/addresses/{$address->public_id}", ['name' => 'Hacked', 'line1' => 'x', 'city' => 'x', 'country' => 'FR'])->assertNotFound();
        $this->deleteJson("/api/v1/addresses/{$address->public_id}")->assertNotFound();
        $this->postJson('/api/v1/shipments', ['draft_id' => $draft->public_id])->assertNotFound();
        $this->assertSame('Owner', $address->fresh()->name);

        $this->useWebGuard();
        $this->get(route('en.account.shipments.show', $order->shipment))->assertNotFound();
        $this->post(route('en.account.orders.cancel', $order))->assertNotFound();
    }

    #[Test]
    public function private_files_need_both_a_valid_signature_and_the_right_user(): void
    {
        $owner = User::factory()->customer()->create();
        $file = app(FileStorageService::class)->storeUpload($this->pngProof(), 'payment_proofs', $owner, ['image/png'], 1024);
        $file->forceFill(['scan_status' => 'clean'])->save();
        $signed = app(FileStorageService::class)->temporaryUrl($file);

        $this->actingAs($owner)->get($signed)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->customer()->create())->get($signed)->assertNotFound();
        $this->actingAs(User::factory()->staff('payment_verifier')->create())->get($signed)->assertOk();
        $this->actingAs(User::factory()->staff('support_agent')->create())->get($signed)->assertNotFound();

        $this->actingAs($owner)->get(route('files.show', ['file' => $file->public_id]))->assertForbidden();
        $this->travel(6)->minutes();
        $this->actingAs($owner)->get($signed)->assertForbidden();
    }

    #[Test]
    public function customers_and_staff_without_2fa_cannot_open_the_back_office(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff('admin')->create())->get('/admin')->assertOk();

        $staffWithout2fa = User::factory()->withRole('admin')->create(['password' => 'Correct-Horse-9']);
        $this->actingAs($staffWithout2fa)->get('/admin')->assertForbidden();

        $this->post('/en/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $staffWithout2fa->email, 'password' => 'Correct-Horse-9'])
            ->assertRedirect(route('en.account.profile', ['setup2fa' => 1]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function backOfficeMatrix(): array
    {
        return [
            'support sees shipments' => ['support_agent', '/admin/shipments', 200],
            'support blocked from proofs' => ['support_agent', '/admin/payment-proofs', 403],
            'support blocked from payment methods' => ['support_agent', '/admin/payment-methods', 403],
            'support blocked from settings' => ['support_agent', '/admin/settings', 403],
            'verifier sees proofs' => ['payment_verifier', '/admin/payment-proofs', 200],
            'verifier blocked from rates' => ['payment_verifier', '/admin/rate-cards', 403],
            'verifier blocked from users' => ['payment_verifier', '/admin/users', 403],
            'verifier blocked from payment methods' => ['payment_verifier', '/admin/payment-methods', 403],
            'verifier blocked from audit log' => ['payment_verifier', '/admin/audit-logs', 403],
            'admin sees audit log' => ['admin', '/admin/audit-logs', 200],
            'admin sees payment methods' => ['admin', '/admin/payment-methods', 200],
        ];
    }

    #[Test]
    #[DataProvider('backOfficeMatrix')]
    public function back_office_screens_follow_role_permissions(string $role, string $url, int $status): void
    {
        $this->actingAs(User::factory()->staff($role)->create())->get($url)->assertStatus($status);
    }

    #[Test]
    public function preview_and_signed_routes_reject_tampering(): void
    {
        $user = User::factory()->customer()->create();
        $url = URL::signedRoute('notifications.unsubscribe', ['user' => $user->public_id, 'event' => 'ticket.reply']);

        $this->get(str_replace('ticket.reply', 'shipment.status_changed', $url))->assertForbidden();
        $this->actingAs($user)->get(route('pages.preview', 1))->assertNotFound();
    }
}
