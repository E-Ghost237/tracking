<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\ShipmentDraft;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Pricing\QuoteService;
use App\Services\ReferenceGenerator;
use App\Services\SequenceGenerator;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Turns a completed booking draft into an order (awaiting_payment, unique payment reference)
 * and a shipment without a tracking number (FR-31). The price comes from the saved quote (BR-02).
 */
class BookingService
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly ReferenceGenerator $references,
        private readonly SequenceGenerator $sequences,
        private readonly Settings $settings,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function book(ShipmentDraft $draft, User $customer): Order
    {
        if ($draft->user_id !== $customer->id) {
            throw new DomainRuleException('forbidden', __('This draft does not belong to you.'), 403);
        }

        $data = $draft->data;
        foreach (['route', 'packages', 'service', 'parties'] as $section) {
            if (empty($data[$section])) {
                throw new DomainRuleException('draft_incomplete', __('Please complete every step before continuing to payment.'));
            }
        }
        if (empty($data['review']['accepted_terms'])) {
            throw new DomainRuleException('terms_required', __('Please accept the terms of service.'));
        }

        $this->assertNoProhibitedItems($data['packages']);

        $quoteInput = $this->quoteInput($data);
        $quote = $this->reusableQuote($draft, $customer, $quoteInput) ?? $this->quotes->create($quoteInput, $customer);

        $order = DB::transaction(function () use ($draft, $customer, $data, $quote): Order {
            $quote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if (! $quote->isBookable()) {
                throw new DomainRuleException('quote_not_bookable', __('This quote has expired or was already booked. Please request a new quote.'), 409);
            }

            $order = new Order;
            $order->forceFill([
                'number' => $this->sequences->orderNumber(),
                'user_id' => $customer->id,
                'quote_id' => $quote->id,
                'created_by' => $customer->id,
                'subtotal' => $quote->total,
                'fee' => 0,
                'total' => $quote->total,
                'currency' => $quote->currency,
                'amount_paid' => 0,
                'payment_reference' => $this->references->unique('PAY', fn (string $ref) => Order::query()->where('payment_reference', $ref)->exists()),
                'status' => OrderStatus::AwaitingPayment,
                'expires_at' => now()->addHours($this->settings->int('payment_expiry_hours')),
            ])->save();

            $route = $data['route'];
            $parties = $data['parties'];
            $service = $data['service'];
            $carrier = Carrier::query()->where('is_own', true)->orderBy('id')->firstOrFail();

            $shipment = new Shipment;
            $shipment->forceFill([
                'carrier_id' => $carrier->id,
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'service' => $this->serviceName($quote->mode),
                'mode' => $quote->mode,
                'origin' => $this->address($route['origin']),
                'destination' => $this->address($route['destination']),
                'sender' => $this->party($parties['sender']),
                'recipient' => $this->party($parties['recipient']),
                'customs' => $parties['customs'] ?? null,
                'status' => ShipmentStatus::AwaitingPayment,
                'weight_g' => (int) round(array_sum(array_column($data['packages'], 'weight_kg')) * 1000),
                'chargeable_weight_g' => (int) round($quote->chargeable_weight_kg * 1000),
                'declared_value' => (int) ($service['declared_value'] ?? 0),
                'currency' => 'USD',
                'insurance' => (bool) ($service['insurance'] ?? false),
                'eta_at' => now()->addDays($quote->transit_max_days),
            ])->save();

            foreach ($data['packages'] as $package) {
                $shipment->packages()->save(new Package([
                    'description' => (string) $package['description'],
                    'weight_g' => (int) round($package['weight_kg'] * 1000),
                    'length_mm' => (int) round($package['length_cm'] * 10),
                    'width_mm' => (int) round($package['width_cm'] * 10),
                    'height_mm' => (int) round($package['height_cm'] * 10),
                    'declared_value' => Money::fromMajor($package['value'] ?? 0),
                    'category' => (string) $package['category'],
                ]));
            }

            $quote->forceFill(['booked_at' => now()])->save();
            $draft->delete();

            $this->audit->log('order.created', $order, null, ['total' => $order->total, 'quote' => $quote->reference], $customer);

            return $order;
        });

        $locale = $customer->preferredLocale();
        $this->notifications->send('booking.created', $customer, [
            'order_number' => $order->number,
            'reference' => $order->payment_reference,
            'amount' => Money::format($order->total, $order->currency, $locale),
            'expires_at' => $order->expires_at->locale($locale)->isoFormat('LLL').' UTC',
            'pay_url' => route($locale.'.account.orders.pay', $order),
        ]);

        return $order;
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     */
    public function assertNoProhibitedItems(array $packages): void
    {
        $categories = config('platform.package_categories');
        foreach ($packages as $package) {
            $category = (string) ($package['category'] ?? '');
            if (! isset($categories[$category]) || ($categories[$category]['prohibited'] ?? false)) {
                throw new DomainRuleException('prohibited_item', __('One of your packages is in a category we cannot carry. See the prohibited items list.'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function quoteInput(array $data): array
    {
        return [
            'origin' => $data['route']['origin'],
            'destination' => $data['route']['destination'],
            'packages' => array_map(fn (array $p) => [
                'weight_kg' => (float) $p['weight_kg'],
                'length_cm' => (float) $p['length_cm'],
                'width_cm' => (float) $p['width_cm'],
                'height_cm' => (float) $p['height_cm'],
            ], $data['packages']),
            'mode' => $data['service']['mode'],
            'declared_value' => (int) ($data['service']['declared_value'] ?? 0),
            'insurance' => (bool) ($data['service']['insurance'] ?? false),
        ];
    }

    /**
     * A saved quote is honoured only if it is still valid, belongs to the customer and
     * matches what is being booked.
     *
     * @param  array<string, mixed>  $input
     */
    private function reusableQuote(ShipmentDraft $draft, User $customer, array $input): ?Quote
    {
        if ($draft->quote_id === null) {
            return null;
        }

        $quote = Quote::query()->find($draft->quote_id);
        if ($quote === null || ! $quote->isBookable() || ($quote->user_id !== null && $quote->user_id !== $customer->id)) {
            return null;
        }

        $same = $quote->mode === $input['mode']
            && $quote->insurance === $input['insurance']
            && $quote->declared_value === $input['declared_value']
            && strtoupper($quote->origin['country']) === strtoupper($input['origin']['country'])
            && strtoupper($quote->destination['country']) === strtoupper($input['destination']['country'])
            && abs(($quote->origin['lat'] ?? 0) - (float) $input['origin']['lat']) < 0.5
            && abs(($quote->destination['lat'] ?? 0) - (float) $input['destination']['lat']) < 0.5
            && $quote->packages == $input['packages'];

        if (! $same) {
            return null;
        }

        if ($quote->user_id === null) {
            $quote->forceFill(['user_id' => $customer->id])->save();
        }

        return $quote;
    }

    private function serviceName(string $mode): string
    {
        return match ($mode) {
            'sea' => 'Sea freight',
            'road' => 'Road freight',
            'express' => 'Express air',
            default => 'Air freight',
        };
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function address(array $address): array
    {
        return [
            'line1' => (string) ($address['line1'] ?? ''),
            'line2' => (string) ($address['line2'] ?? ''),
            'city' => (string) $address['city'],
            'region' => (string) ($address['region'] ?? ''),
            'postal_code' => (string) ($address['postal_code'] ?? ''),
            'country' => strtoupper((string) $address['country']),
            'lat' => round((float) $address['lat'], 6),
            'lon' => round((float) $address['lon'], 6),
        ];
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, string>
     */
    private function party(array $party): array
    {
        return [
            'name' => (string) $party['name'],
            'company' => (string) ($party['company'] ?? ''),
            'phone' => (string) $party['phone'],
            'email' => (string) ($party['email'] ?? ''),
        ];
    }
}
