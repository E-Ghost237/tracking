<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Services\Pricing\QuoteCalculator;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Public price guide computed from the active rate card (section 3.1).
 */
class RatesController extends Controller
{
    private const LANES = [
        ['Houston', 'US', 29.7604, -95.3698, 'Paris', 'FR', 48.8566, 2.3522],
        ['New York', 'US', 40.7128, -74.006, 'London', 'GB', 51.5072, -0.1276],
        ['Paris', 'FR', 48.8566, 2.3522, 'Houston', 'US', 29.7604, -95.3698],
        ['Lagos', 'NG', 6.5244, 3.3792, 'London', 'GB', 51.5072, -0.1276],
        ['New York', 'US', 40.7128, -74.006, 'Accra', 'GH', 5.6037, -0.1870],
        ['Paris', 'FR', 48.8566, 2.3522, 'Brussels', 'BE', 50.8503, 4.3517],
    ];

    private const WEIGHTS = [1, 5, 10, 25, 50];

    public function __invoke(QuoteCalculator $calculator): View
    {
        $locale = app()->getLocale();

        $table = Cache::remember('rates-guide:'.$locale, now()->addMinutes(10), function () use ($calculator, $locale): array {
            $rows = [];
            foreach (self::LANES as [$fromCity, $fromCountry, $fromLat, $fromLon, $toCity, $toCountry, $toLat, $toLon]) {
                foreach (['air', 'sea', 'road'] as $mode) {
                    $prices = [];
                    $transit = null;
                    foreach (self::WEIGHTS as $kg) {
                        try {
                            $result = $calculator->calculate(
                                ['city' => $fromCity, 'country' => $fromCountry, 'lat' => $fromLat, 'lon' => $fromLon],
                                ['city' => $toCity, 'country' => $toCountry, 'lat' => $toLat, 'lon' => $toLon],
                                [['weight_kg' => $kg, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10]],
                                $mode, 0, false,
                            );
                            $prices[$kg] = Money::format($result['total'], 'USD', $locale);
                            $transit = $result['transit_min_days'].'–'.$result['transit_max_days'];
                        } catch (DomainRuleException) {
                            $prices[$kg] = null;
                        }
                    }
                    if (array_filter($prices)) {
                        $rows[] = ['from' => $fromCity, 'to' => $toCity, 'mode' => $mode, 'prices' => $prices, 'transit' => $transit];
                    }
                }
            }

            return $rows;
        });

        return view('pages.rates', ['rows' => $table, 'weights' => self::WEIGHTS]);
    }
}
