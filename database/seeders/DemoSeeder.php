<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Alert;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodField;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\ReferenceGenerator;
use App\Services\SequenceGenerator;
use Illuminate\Database\Seeder;

/**
 * Local and staging demo data only. Never run in production (DatabaseSeeder guards it).
 * Payment details below are obviously fake placeholders.
 */
class DemoSeeder extends Seeder
{
    /**
     * Known TOTP secrets so developers can add the staff accounts to an authenticator app.
     */
    public const STAFF = [
        ['admin@corvane.test', 'Ada Admin', 'admin', 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'],
        ['verifier@corvane.test', 'Victor Verifier', 'payment_verifier', 'KRSXG5CTMVRXEZLUKRSXG5CTMVRXEZLU'],
        ['verifier2@corvane.test', 'Valerie Verifier', 'payment_verifier', 'MFRGGZDFMZTWQ2LKMFRGGZDFMZTWQ2LK'],
        ['support@corvane.test', 'Sam Support', 'support_agent', 'ONSWG4TFORXXEZLTONSWG4TFORXXEZLT'],
    ];

    public const PASSWORD = 'Corvane-Demo-2026!';

    public function run(): void
    {
        foreach (self::STAFF as [$email, $name, $role, $secret]) {
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'locale' => 'en']);
            $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
            $user->roles()->syncWithoutDetaching([Role::query()->where('slug', $role)->value('id')]);
        }

        $customer = User::query()->firstOrCreate(['email' => 'customer@corvane.test'], ['name' => 'Chantal Mbarga', 'password' => self::PASSWORD, 'locale' => 'en', 'phone' => '+1 713 555 0123']);
        $customer->forceFill(['email_verified_at' => now(), 'created_at' => now()->subDays(60)])->save();
        $customer->roles()->syncWithoutDetaching([Role::query()->where('slug', 'customer')->value('id')]);

        $this->paymentMethods();
        $this->shipments($customer);


    }

    private function paymentMethods(): void
    {
        $demo = [
            'zelle' => [['Email', 'E-mail', 'payments@corvane.test', 'copy'], ['Account holder', 'Titulaire', 'Corvane Logistics (DEMO)', 'text']],
            'cashapp' => [['$Cashtag', '$Cashtag', '$CorvaneDemo', 'copy'], ['Display name', 'Nom affiché', 'Corvane DEMO', 'text']],
            'iban' => [['Account holder', 'Titulaire', 'Corvane Logistics (DEMO)', 'copy'], ['IBAN', 'IBAN', 'FR00 DEMO 0000 0000 0000 0000 000', 'copy'], ['BIC', 'BIC', 'DEMOFRPPXXX', 'copy'], ['Bank', 'Banque', 'Demo Bank', 'text']],
            'paypal' => [['PayPal email', 'E-mail PayPal', 'pay@corvane.test', 'copy'], ['Payment type', 'Type de paiement', 'Choose "Goods and services" when you send the payment.', 'note']],
        ];

        foreach ($demo as $slug => $fields) {
            $method = PaymentMethod::query()->where('slug', $slug)->first();
            if ($method === null || $method->fields()->exists()) {
                continue;
            }
            $method->forceFill([
                'is_enabled' => true,
                'fee_percent' => $slug === 'paypal' ? 3.5 : 0,
                'instructions_en' => 'Send the exact amount and write your payment reference in the note. Keep a screenshot of the confirmation.',
                'instructions_fr' => 'Envoyez le montant exact en indiquant votre référence de paiement dans le motif. Gardez une capture de la confirmation.',
            ])->save();
            foreach ($fields as $index => [$labelEn, $labelFr, $value, $type]) {
                PaymentMethodField::query()->create([
                    'payment_method_id' => $method->id, 'label_en' => $labelEn, 'label_fr' => $labelFr, 'value' => $value, 'type' => $type, 'sort_order' => $index,
                ]);
            }
        }
    }

    private function shipments(User $customer): void
    {
        if (Shipment::query()->whereNotNull('tracking_number')->exists()) {
            return;
        }

        $sequences = app(SequenceGenerator::class);
        $references = app(ReferenceGenerator::class);
        $carrier = Carrier::query()->where('is_own', true)->firstOrFail();
        $places = [
            'houston' => ['line1' => '1000 Shipping Lane', 'city' => 'Houston', 'country' => 'US', 'lat' => 29.7604, 'lon' => -95.3698],
            'paris' => ['line1' => '12 rue de Rivoli', 'city' => 'Paris', 'country' => 'FR', 'lat' => 48.8566, 'lon' => 2.3522],
            'guangzhou' => ['line1' => 'Baiyun District', 'city' => 'Guangzhou', 'country' => 'CN', 'lat' => 23.1291, 'lon' => 113.2644],
            'brussels' => ['line1' => 'Avenue Louise 54', 'city' => 'Brussels', 'country' => 'BE', 'lat' => 50.8503, 'lon' => 4.3517],
            'newyork' => ['line1' => '350 Fifth Avenue', 'city' => 'New York', 'country' => 'US', 'lat' => 40.7128, 'lon' => -74.006],
            'london' => ['line1' => '10 Downing Street', 'city' => 'London', 'country' => 'GB', 'lat' => 51.5072, 'lon' => -0.1276],
        ];

        $scenarios = [
            ['air', 'houston', 'paris', 42500, ShipmentStatus::InTransit, 0.55, [
                [ShipmentStatus::Ready, 'Shipment registered, label created', 'Houston, US', 29.7604, -95.3698, 72],
                [ShipmentStatus::PickedUp, 'Picked up at sender address', 'Houston, US', 29.7604, -95.3698, 60],
                [ShipmentStatus::InTransit, 'Departed origin airport', 'Houston, US', 29.7604, -95.3698, 30],
                [ShipmentStatus::InTransit, 'Arrived at destination airport', 'Paris CDG, FR', 49.0097, 2.5479, 6],
            ]],
            ['sea', 'guangzhou', 'newyork', 186000, ShipmentStatus::InTransit, 0.4, [
                [ShipmentStatus::Ready, 'Shipment registered, label created', 'Guangzhou, CN', 23.1291, 113.2644, 400],
                [ShipmentStatus::PickedUp, 'Received at origin warehouse', 'Guangzhou, CN', 23.1291, 113.2644, 380],
                [ShipmentStatus::InTransit, 'Loaded on vessel, departed port', 'Nansha Port, CN', 22.75, 113.6, 300],
                [ShipmentStatus::InTransit, 'Vessel in transit, Pacific Ocean', 'At sea', -2.0, 150.0, 120],
            ]],
            ['road', 'paris', 'london', 9800, ShipmentStatus::Delivered, 1.0, [
                [ShipmentStatus::Ready, 'Shipment registered, label created', 'Paris, FR', 48.8566, 2.3522, 120],
                [ShipmentStatus::PickedUp, 'Picked up at sender address', 'Paris, FR', 48.8566, 2.3522, 100],
                [ShipmentStatus::OutForDelivery, 'Out for delivery', 'London, GB', 51.5072, -0.1276, 52],
                [ShipmentStatus::Delivered, 'Delivered, signed by recipient', 'London, GB', 51.5072, -0.1276, 48],
            ]],
        ];

        foreach ($scenarios as [$mode, $from, $to, $total, $status, $progress, $events]) {
            $quote = Quote::query()->create([
                'reference' => $references->make('Q'), 'user_id' => $customer->id, 'origin' => $places[$from], 'destination' => $places[$to],
                'packages' => [['weight_kg' => 12, 'length_cm' => 40, 'width_cm' => 30, 'height_cm' => 30]], 'mode' => $mode, 'insurance' => false,
                'declared_value' => 20000, 'chargeable_weight_kg' => 12, 'distance_km' => 5000, 'price_breakdown' => ['breakdown' => ['freight' => $total, 'surcharges' => []]],
                'total' => $total, 'currency' => 'USD', 'transit_min_days' => 4, 'transit_max_days' => 8, 'expires_at' => now()->addDays(7), 'booked_at' => now()->subDays(5),
            ]);

            $order = new Order;
            $order->forceFill([
                'number' => $sequences->orderNumber(), 'user_id' => $customer->id, 'quote_id' => $quote->id, 'created_by' => $customer->id,
                'subtotal' => $total, 'fee' => 0, 'total' => $total, 'currency' => 'USD', 'amount_paid' => $total,
                'payment_reference' => $references->make('PAY'), 'status' => OrderStatus::Paid, 'paid_at' => now()->subDays(4),
                'receipt_number' => $sequences->receiptNumber(),
            ])->save();

            $shipment = new Shipment;
            $shipment->forceFill([
                'tracking_number' => $sequences->trackingNumber($mode), 'carrier_id' => $carrier->id, 'order_id' => $order->id, 'user_id' => $customer->id,
                'service' => ['air' => 'Air freight', 'sea' => 'Sea freight', 'road' => 'Road freight'][$mode], 'mode' => $mode,
                'origin' => $places[$from], 'destination' => $places[$to],
                'sender' => ['name' => 'Chantal Mbarga', 'phone' => '+1 713 555 0123', 'email' => '', 'company' => ''],
                'recipient' => ['name' => 'Jean Dupont', 'phone' => '+33 6 00 00 00 00', 'email' => '', 'company' => ''],
                'status' => $status, 'weight_g' => 12000, 'chargeable_weight_g' => 12000, 'declared_value' => 20000, 'progress' => $progress,
                'eta_at' => now()->addDays($status === ShipmentStatus::Delivered ? -2 : 3), 'released_at' => now()->subDays(4),
                'delivered_at' => $status === ShipmentStatus::Delivered ? now()->subHours(48) : null,
            ])->save();
            $shipment->packages()->save(new Package(['description' => 'Clothes and personal effects', 'weight_g' => 12000, 'length_mm' => 400, 'width_mm' => 300, 'height_mm' => 300, 'declared_value' => 20000, 'category' => 'clothing']));

            foreach ($events as [$eventStatus, $label, $place, $lat, $lon, $hoursAgo]) {
                $event = new ShipmentEvent;
                $event->forceFill([
                    'shipment_id' => $shipment->id, 'status' => $eventStatus, 'label' => $label, 'place' => $place, 'lat' => $lat, 'lon' => $lon,
                    'occurred_at' => now()->subHours($hoursAgo), 'source' => 'admin', 'is_public' => true,
                ])->save();
            }

            $last = end($events);
            $shipment->forceFill(['current_lat' => $last[3], 'current_lon' => $last[4], 'current_place' => $last[2]])->save();
        }
    }
}
