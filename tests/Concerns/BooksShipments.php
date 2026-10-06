<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodField;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Drives the real booking and payment API the way the website does.
 */
trait BooksShipments
{
    /**
     * @return array<string, mixed>
     */
    protected function route(): array
    {
        return [
            'origin' => ['line1' => 'Rue Joss', 'city' => 'Douala', 'country' => 'CM', 'lat' => 4.0511, 'lon' => 9.7679],
            'destination' => ['line1' => '12 rue de Rivoli', 'city' => 'Paris', 'country' => 'FR', 'lat' => 48.8566, 'lon' => 2.3522],
        ];
    }

    protected function bookOrder(User $customer, float $weightKg = 12): Order
    {
        $this->actingAs($customer);

        $draft = $this->postJson('/api/v1/shipment-drafts')->assertCreated()->json('id');
        $steps = [
            'route' => $this->route(),
            'packages' => ['packages' => [[
                'description' => 'Clothes and shoes', 'weight_kg' => $weightKg, 'length_cm' => 40, 'width_cm' => 30,
                'height_cm' => 30, 'value' => 150, 'category' => 'clothing',
            ]]],
            'service' => ['mode' => 'air', 'insurance' => false],
            'parties' => [
                'sender' => ['name' => 'Chantal Mbarga', 'phone' => '+237 699 000 000'],
                'recipient' => ['name' => 'Jean Dupont', 'phone' => '+33 6 00 00 00 00'],
                'customs' => ['contents' => 'Clothes', 'reason' => 'gift'],
            ],
            'review' => ['accepted_terms' => true],
        ];

        foreach ($steps as $step => $data) {
            $this->putJson("/api/v1/shipment-drafts/$draft", ['step' => $step, 'data' => $data])->assertOk();
        }

        $orderId = $this->postJson('/api/v1/shipments', ['draft_id' => $draft])->assertCreated()->json('order_id');
        $this->useWebGuard();

        return Order::query()->where('public_id', $orderId)->firstOrFail();
    }

    /**
     * API calls switch the default guard to Sanctum for the rest of the test process;
     * real requests start fresh, so tests reset it before hitting web routes.
     */
    protected function useWebGuard(): void
    {
        $this->app['auth']->shouldUse('web');
    }

    protected function enabledMethod(string $slug = 'zelle', string $currency = 'USD', array $values = ['payments@example.test', 'Corvane LLC']): PaymentMethod
    {
        $method = PaymentMethod::query()->where('slug', $slug)->firstOrFail();
        $method->forceFill(['is_enabled' => true, 'currency' => $currency])->save();

        foreach ($values as $index => $value) {
            PaymentMethodField::query()->create([
                'payment_method_id' => $method->id,
                'label_en' => 'Field '.$index,
                'label_fr' => 'Champ '.$index,
                'value' => $value,
                'type' => 'copy',
                'sort_order' => $index,
            ]);
        }

        return $method->refresh();
    }

    /**
     * A small valid PNG with real image bytes.
     */
    protected function pngProof(string $name = 'proof.png', ?string $seed = null): UploadedFile
    {
        $image = imagecreatetruecolor(40, 30);
        $seed ??= Str::random(8);
        imagefilledrectangle($image, 0, 0, 39, 29, imagecolorallocate($image, crc32($seed) % 255, 120, 60));
        imagestring($image, 2, 2, 2, substr($seed, 0, 6), imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function proofPayload(Order $order, ?UploadedFile $file = null, ?float $amount = null): array
    {
        return [
            'files' => [$file ?? $this->pngProof()],
            'amount_paid' => $amount ?? $order->fresh()->total / 100,
            'payer_name' => 'Chantal Mbarga',
            'paid_on' => now()->toDateString(),
            'transaction_id' => 'TX'.Str::upper(Str::random(8)),
        ];
    }
}
