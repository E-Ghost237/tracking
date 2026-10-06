<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\RateCard;
use App\Models\RateLine;
use App\Models\Role;
use App\Models\Surcharge;
use App\Models\TransportMode;
use App\Models\Zone;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference data needed in every environment: roles, carriers, zones, modes, rate card,
 * surcharges, hubs and the payment method catalogue (disabled, without account details).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->rolesAndPermissions();
        $this->carriers();
        $this->pricing();
        $this->locations();
        $this->paymentMethods();
        $this->call(CitySeeder::class);
    }

    private function rolesAndPermissions(): void
    {
        foreach (Permissions::all() as $slug => $meta) {
            Permission::query()->updateOrCreate(['slug' => $slug], $meta);
        }

        foreach (Permissions::defaultRoles() as $slug => $role) {
            $model = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $role['name'], 'is_staff' => $role['is_staff']]);
            $model->permissions()->sync(Permission::query()->whereIn('slug', $role['permissions'])->pluck('id'));
        }
    }

    private function carriers(): void
    {
        $carriers = [
            ['code' => 'corvane', 'name' => config('platform.brand.name'), 'number_patterns' => ['CV(AIR|SEA|RD)[0-9]{6,9}'], 'tracking_url_template' => null, 'region' => 'global', 'is_own' => true, 'sort_order' => 0],
            ['code' => 'ups', 'name' => 'UPS', 'number_patterns' => ['1Z[0-9A-Z]{16}'], 'tracking_url_template' => 'https://www.ups.com/track?tracknum={number}', 'region' => 'us', 'is_own' => false, 'sort_order' => 1],
            ['code' => 'usps', 'name' => 'USPS', 'number_patterns' => ['9[1-5][0-9]{18,20}'], 'tracking_url_template' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={number}', 'region' => 'us', 'is_own' => false, 'sort_order' => 2],
            ['code' => 'fedex', 'name' => 'FedEx', 'number_patterns' => ['[0-9]{12}', '[0-9]{15}'], 'tracking_url_template' => 'https://www.fedex.com/fedextrack/?trknbr={number}', 'region' => 'europe', 'is_own' => false, 'sort_order' => 3],
        ];

        foreach ($carriers as $carrier) {
            Carrier::query()->updateOrCreate(['code' => $carrier['code']], $carrier + ['is_active' => true]);
        }
    }

    private function pricing(): void
    {
        $zones = [
            'CAF' => ['Central Africa', ['CM', 'GA', 'CG', 'CD', 'CF', 'TD', 'GQ', 'ST', 'AO']],
            'WAF' => ['West Africa', ['NG', 'CI', 'SN', 'GH', 'BJ', 'TG', 'ML', 'BF', 'GN', 'NE', 'SL', 'LR', 'GM', 'GW', 'MR', 'CV']],
            'AFR' => ['Rest of Africa', ['KE', 'TZ', 'UG', 'RW', 'BI', 'ET', 'ZA', 'ZM', 'ZW', 'MZ', 'MA', 'DZ', 'TN', 'EG', 'MG', 'NA', 'BW', 'SD', 'DJ']],
            'EU' => ['Europe', ['FR', 'BE', 'DE', 'NL', 'LU', 'ES', 'PT', 'IT', 'CH', 'GB', 'IE', 'AT', 'DK', 'SE', 'NO', 'FI', 'PL', 'CZ', 'GR', 'RO', 'HU', 'SK', 'SI', 'HR', 'BG', 'EE', 'LV', 'LT', 'CY', 'MT', 'MC', 'IS']],
            'NA' => ['North America', ['US', 'CA']],
            'ASIA' => ['Asia and Middle East', ['AE', 'CN', 'HK', 'TR', 'IN', 'SA', 'QA', 'JP', 'KR', 'SG', 'MY', 'TH', 'VN', 'LB', 'IL', 'JO', 'KW', 'OM', 'BH']],
        ];
        foreach ($zones as $code => [$name, $countries]) {
            Zone::query()->updateOrCreate(['code' => $code], ['name' => $name, 'countries' => $countries]);
        }

        $modes = [
            ['code' => 'air', 'name_en' => 'Air freight', 'name_fr' => 'Fret aérien', 'multiplier' => 1.0, 'volumetric_divisor' => 5000, 'sort_order' => 1],
            ['code' => 'express', 'name_en' => 'Express', 'name_fr' => 'Express', 'multiplier' => 1.55, 'volumetric_divisor' => 5000, 'sort_order' => 2],
            ['code' => 'sea', 'name_en' => 'Sea freight', 'name_fr' => 'Fret maritime', 'multiplier' => 1.0, 'volumetric_divisor' => 1000, 'sort_order' => 3],
            ['code' => 'road', 'name_en' => 'Road freight', 'name_fr' => 'Transport routier', 'multiplier' => 1.0, 'volumetric_divisor' => 4000, 'sort_order' => 4],
        ];
        foreach ($modes as $mode) {
            TransportMode::query()->updateOrCreate(['code' => $mode['code']], $mode + ['is_active' => true]);
        }

        Surcharge::query()->updateOrCreate(['code' => 'FUEL'], ['name' => 'Fuel surcharge', 'type' => 'percent', 'value' => 12, 'basis' => 'freight', 'applies_to' => 'all', 'is_active' => true]);
        Surcharge::query()->updateOrCreate(['code' => 'HANDLING'], ['name' => 'Handling', 'type' => 'fixed', 'value' => 5, 'basis' => 'freight', 'applies_to' => 'all', 'is_active' => true]);
        Surcharge::query()->updateOrCreate(['code' => 'INSURANCE'], ['name' => 'Insurance', 'type' => 'percent', 'value' => 2.5, 'basis' => 'declared_value', 'applies_to' => 'all', 'is_active' => true]);

        if (RateCard::query()->exists()) {
            return;
        }

        DB::transaction(function () use ($zones): void {
            $card = RateCard::query()->create(['version' => 1, 'name' => 'Launch rate card', 'valid_from' => now()->subDay(), 'is_active' => true, 'notes' => 'Placeholder rates until the client provides its tables (section 16.1).']);

            $codes = [...array_keys($zones), 'ROW'];
            $continent = ['CAF' => 'africa', 'WAF' => 'africa', 'AFR' => 'africa', 'EU' => 'europe', 'NA' => 'america', 'ASIA' => 'asia', 'ROW' => 'other'];
            $bands = [[0, 5, 1.0], [5, 20, 0.85], [20, 50, 0.72], [50, 200, 0.62], [200, 1000, 0.55], [1000, 3000, 0.5]];
            $lines = [];

            foreach ($codes as $from) {
                foreach ($codes as $to) {
                    $factor = match (true) {
                        $from === $to => 0.55,
                        $continent[$from] === $continent[$to] => 0.8,
                        in_array([$continent[$from], $continent[$to]], [['africa', 'europe'], ['europe', 'africa'], ['europe', 'america'], ['america', 'europe']], true) => 1.25,
                        default => 1.6,
                    };
                    $sameContinent = $continent[$from] === $continent[$to] && $from !== 'ROW';

                    foreach ($bands as [$min, $max, $discount]) {
                        $lines[] = $this->line($card->id, $from, $to, 'air', 2500, (int) round(950 * $factor * $discount), $min, $max, $sameContinent ? [2, 5] : [4, 8]);
                        $lines[] = $this->line($card->id, $from, $to, 'sea', 6500, (int) round(240 * $factor * $discount), $min, $max, $sameContinent ? [10, 20] : [25, 45]);
                        if ($sameContinent) {
                            $lines[] = $this->line($card->id, $from, $to, 'road', 1800, (int) round(170 * $factor * $discount), $min, $max, $from === $to ? [2, 5] : [4, 10]);
                        }
                    }
                }
            }

            foreach (array_chunk($lines, 300) as $chunk) {
                RateLine::query()->insert($chunk);
            }
        });
    }

    /**
     * @param  array{0: int, 1: int}  $transit
     * @return array<string, mixed>
     */
    private function line(int $card, string $from, string $to, string $mode, int $base, int $perKg, float $min, float $max, array $transit): array
    {
        return [
            'rate_card_id' => $card, 'zone_from' => $from, 'zone_to' => $to, 'mode' => $mode, 'base_fee' => $base,
            'weight_from_kg' => $min, 'weight_to_kg' => $max, 'price_per_kg' => $perKg,
            'transit_min_days' => $transit[0], 'transit_max_days' => $transit[1], 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function locations(): void
    {
        $hubs = [
            ['Houston Hub', 'Houston', 'US', 29.7604, -95.3698, ['air', 'sea', 'road'], 'Port of Houston'],
            ['Paris CDG Hub', 'Paris', 'FR', 48.8566, 2.3522, ['air', 'road'], 'Zone de fret, Roissy'],
            ['Le Havre Port', 'Le Havre', 'FR', 49.4944, 0.1079, ['sea'], 'Port 2000'],
            ['Brussels Hub', 'Brussels', 'BE', 50.8503, 4.3517, ['air', 'road'], 'Brucargo'],
            ['London Hub', 'London', 'GB', 51.5072, -0.1276, ['air'], 'Heathrow cargo area'],
            ['New York Hub', 'New York', 'US', 40.7128, -74.006, ['air', 'road'], 'JFK cargo area'],
            ['Atlanta Hub', 'Atlanta', 'US', 33.749, -84.388, ['air', 'road'], 'ATL cargo city'],
            ['Montreal Hub', 'Montreal', 'CA', 45.5019, -73.5674, ['air'], 'YUL cargo'],
            ['Lagos Hub', 'Lagos', 'NG', 6.5244, 3.3792, ['air', 'sea', 'road'], 'Ikeja'],
            ['Abidjan Hub', 'Abidjan', 'CI', 5.36, -4.0083, ['air', 'sea', 'road'], 'Port-Bouët'],
            ['Dakar Hub', 'Dakar', 'SN', 14.7167, -17.4677, ['air', 'road'], 'Aéroport de Dakar'],
            ['Dubai Hub', 'Dubai', 'AE', 25.2048, 55.2708, ['air', 'sea'], 'Dubai South'],
            ['Guangzhou Hub', 'Guangzhou', 'CN', 23.1291, 113.2644, ['air', 'sea'], 'Baiyun'],
        ];

        foreach ($hubs as $index => [$name, $city, $country, $lat, $lon, $modes, $line]) {
            Location::query()->updateOrCreate(['name' => $name], [
                'type' => 'hub', 'line1' => $line, 'city' => $city, 'country' => $country, 'lat' => $lat, 'lon' => $lon,
                'modes' => $modes, 'opening_hours' => ['Mon–Fri 08:00–18:00', 'Sat 09:00–13:00'], 'is_active' => true, 'sort_order' => $index,
            ]);
        }

        $points = [
            ['Saint-Denis drop-off point', 'Saint-Denis', 'FR', 48.9362, 2.3574, 'Rue de la République'],
            ['Brooklyn drop-off point', 'New York', 'US', 40.6782, -73.9442, 'Fulton Street'],
            ['Hoxton drop-off point', 'London', 'GB', 51.5440, -0.0915, 'Shoreditch High Street'],
            ['Barcelona drop-off point', 'Barcelona', 'ES', 41.3874, 2.1686, 'La Rambla'],
        ];
        foreach ($points as [$name, $city, $country, $lat, $lon, $line]) {
            Location::query()->updateOrCreate(['name' => $name], [
                'type' => 'dropoff', 'line1' => $line, 'city' => $city, 'country' => $country, 'lat' => $lat, 'lon' => $lon,
                'modes' => ['air', 'sea'], 'opening_hours' => ['Mon–Sat 09:00–19:00'], 'is_active' => true, 'sort_order' => 50,
            ]);
        }
    }

    /**
     * Launch catalogue (section 5.1). All disabled and without account details: the admin
     * configures and enables each method (FR-50 to FR-52, R2). Gift cards stay off (FR-90).
     */
    private function paymentMethods(): void
    {
        $methods = [
            ['cashapp', 'Cash App', 'USD', false, 'low'],
            ['zelle', 'Zelle', 'USD', false, 'low'],
            ['venmo', 'Venmo', 'USD', false, 'low'],
            ['chime', 'Chime', 'USD', false, 'low'],
            ['apple_pay', 'Apple Pay', 'USD', false, 'medium'],
            ['google_pay', 'Google Pay', 'USD', false, 'medium'],
            ['iban', 'IBAN transfer', 'EUR', true, 'low'],
            ['paypal', 'PayPal', 'USD', true, 'medium'],
            ['gift_card', 'Gift cards', 'USD', false, 'high'],
        ];

        foreach ($methods as $index => [$kind, $name, $currency, $requiresTx, $risk]) {
            PaymentMethod::query()->firstOrCreate(['slug' => str_replace('_', '-', $kind)], [
                'name' => $name,
                'kind' => $kind,
                'is_enabled' => false,
                'sort_order' => $index,
                'currency' => $currency,
                'min_amount' => 0,
                'fee_percent' => 0,
                'fee_fixed' => 0,
                'requires_transaction_id' => $requiresTx,
                'proof_required' => true,
                'risk_level' => $risk,
                'expiry_hours' => null,
                'gift_card_rules' => $kind === 'gift_card' ? ['brands' => [], 'per_card_min' => 2500, 'per_card_max' => 20000, 'daily_limit' => 50000, 'min_account_age_days' => 30] : null,
            ]);
        }
    }
}
