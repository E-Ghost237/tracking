<?php

namespace App\Services\Pricing;

use App\Exceptions\DomainRuleException;
use App\Models\RateCard;
use App\Models\RateLine;
use App\Models\Surcharge;
use App\Models\TransportMode;
use App\Models\Zone;
use App\Services\Settings;
use App\Support\Geo;

/**
 * Prices a shipment from admin-managed zones, weight bands, mode multipliers and surcharges (FR-20 to FR-24).
 *
 *   chargeable = max(actual, L x W x H / divisor)   (per package, summed)
 *   P = (B_zone + c_band x chargeable) x m_mode + S_fuel + S_insurance + S_handling
 */
class QuoteCalculator
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array{city: string, country: string, lat: float, lon: float}  $origin
     * @param  array{city: string, country: string, lat: float, lon: float}  $destination
     * @param  array<int, array{weight_kg: float, length_cm: float, width_cm: float, height_cm: float}>  $packages
     * @return array<string, mixed>
     */
    public function calculate(array $origin, array $destination, array $packages, string $mode, int $declaredValue, bool $insurance): array
    {
        $transportMode = TransportMode::query()->where('code', $mode)->where('is_active', true)->first();
        if ($transportMode === null) {
            throw new DomainRuleException('mode_unavailable', __('This transport mode is not available.'));
        }

        $distance = Geo::distanceKm((float) $origin['lat'], (float) $origin['lon'], (float) $destination['lat'], (float) $destination['lon']);

        if ($mode === 'road') {
            $maxKm = $this->settings->int('road_max_km');
            if (! Geo::sameLandmass($origin['country'], $destination['country']) || $distance > $maxKm) {
                throw new DomainRuleException('road_unavailable', __('Road freight is not possible between these two points. Try air or sea freight instead.'), 422, ['suggest' => ['air', 'sea']]);
            }
        }

        [$actualKg, $volumetricKg, $chargeableKg] = $this->weights($packages, $transportMode->volumetric_divisor);

        $rateCard = RateCard::current();
        if ($rateCard === null) {
            throw new DomainRuleException('no_rate_card', __('Pricing is temporarily unavailable. Please contact us for a quote.'), 503);
        }

        $zoneFrom = $this->zoneFor($origin['country']);
        $zoneTo = $this->zoneFor($destination['country']);
        $lineMode = $mode === 'express' ? 'air' : $mode;
        $line = $this->findLine($rateCard, $zoneFrom, $zoneTo, $lineMode, $chargeableKg);

        if ($line === null) {
            throw new DomainRuleException('no_rate', __('We do not have a published rate for this route yet. Please contact us for a custom quote.'));
        }

        $freight = (int) round(($line->base_fee + $line->price_per_kg * $chargeableKg) * $transportMode->multiplier);
        $minimum = $this->settings->int('minimum_charge');
        $minimumApplied = $freight < $minimum;
        $freight = max($freight, $minimum);

        $surcharges = [];
        $surchargeTotal = 0;
        $active = Surcharge::query()->where('is_active', true)->whereIn('applies_to', ['all', $mode])->orderBy('id')->get();
        foreach ($active as $surcharge) {
            if ($surcharge->basis === 'declared_value' && ! $insurance) {
                continue;
            }
            $basis = $surcharge->basis === 'declared_value' ? $declaredValue : $freight;
            $amount = $surcharge->type === 'percent'
                ? (int) round($basis * $surcharge->value / 100)
                : (int) round($surcharge->value * 100);
            if ($amount <= 0) {
                continue;
            }
            $surcharges[] = ['code' => $surcharge->code, 'name' => $surcharge->name, 'amount' => $amount];
            $surchargeTotal += $amount;
        }

        $total = $freight + $surchargeTotal;
        $range = (int) $this->settings->get('quote_range_percent', 0);

        [$transitMin, $transitMax] = $mode === 'express'
            ? [max(1, (int) ceil($line->transit_min_days / 2)), max(2, (int) ceil($line->transit_max_days / 2))]
            : [$line->transit_min_days, $line->transit_max_days];

        return [
            'mode' => $mode,
            'currency' => 'USD',
            'distance_km' => $distance,
            'actual_weight_kg' => $actualKg,
            'volumetric_weight_kg' => $volumetricKg,
            'chargeable_weight_kg' => $chargeableKg,
            'zone_from' => $zoneFrom,
            'zone_to' => $zoneTo,
            'rate_card_id' => $rateCard->id,
            'rate_card_version' => $rateCard->version,
            'breakdown' => [
                'base_fee' => $line->base_fee,
                'price_per_kg' => $line->price_per_kg,
                'mode_multiplier' => $transportMode->multiplier,
                'freight' => $freight,
                'minimum_applied' => $minimumApplied,
                'surcharges' => $surcharges,
            ],
            'total' => $total,
            'price_min' => $total,
            'price_max' => (int) round($total * (1 + $range / 100)),
            'transit_min_days' => $transitMin,
            'transit_max_days' => $transitMax,
            'network' => Geo::networkRegion($destination['country']),
            'network_label' => Geo::networkLabel($destination['country']),
        ];
    }

    /**
     * @param  array<int, array{weight_kg: float, length_cm: float, width_cm: float, height_cm: float}>  $packages
     * @return array{0: float, 1: float, 2: float}
     */
    public function weights(array $packages, int $divisor = 5000): array
    {
        $actual = 0.0;
        $volumetric = 0.0;
        $chargeable = 0.0;

        foreach ($packages as $package) {
            $weight = (float) $package['weight_kg'];
            $volume = ((float) $package['length_cm'] * (float) $package['width_cm'] * (float) $package['height_cm']) / max(1, $divisor);
            $actual += $weight;
            $volumetric += $volume;
            $chargeable += max($weight, $volume);
        }

        // Round chargeable weight up to the next half kilogram, as carriers do.
        $chargeable = ceil($chargeable * 2) / 2;

        return [round($actual, 2), round($volumetric, 2), max(0.5, $chargeable)];
    }

    public function zoneFor(string $country): string
    {
        $country = strtoupper($country);
        $zones = Zone::query()->orderBy('id')->get(['code', 'countries']);

        foreach ($zones as $zone) {
            if (in_array($country, $zone->countries ?? [], true)) {
                return $zone->code;
            }
        }

        return 'ROW';
    }

    private function findLine(RateCard $card, string $from, string $to, string $mode, float $weight): ?RateLine
    {
        $candidates = [[$from, $to], [$to, $from], [$from, 'ROW'], ['ROW', $to], ['ROW', 'ROW']];

        foreach ($candidates as [$zoneFrom, $zoneTo]) {
            $line = RateLine::query()
                ->where('rate_card_id', $card->id)
                ->where('zone_from', $zoneFrom)
                ->where('zone_to', $zoneTo)
                ->where('mode', $mode)
                ->where('weight_from_kg', '<=', $weight)
                ->where('weight_to_kg', '>=', $weight)
                ->orderBy('weight_from_kg')
                ->first();

            if ($line !== null) {
                return $line;
            }
        }

        return null;
    }
}
