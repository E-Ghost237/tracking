<?php

namespace App\Support;

use Locale;

/**
 * Geography helpers: distances, landmasses for the road rule (FR-23) and the
 * last-mile network shown for a destination (FR-24, FR-44).
 */
final class Geo
{
    /**
     * Countries grouped by connected landmass. Road freight needs origin and destination on the same one.
     *
     * @var array<string, array<int, string>>
     */
    private const LANDMASSES = [
        'afro_eurasia' => [
            // Africa (mainland)
            'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CF', 'TD', 'CD', 'CG', 'CI', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM',
            'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MW', 'ML', 'MR', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW', 'SN', 'SL', 'SO', 'ZA',
            'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW', 'EH',
            // Europe (mainland)
            'AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IT', 'LV', 'LI', 'LT',
            'LU', 'MK', 'MD', 'MC', 'ME', 'NL', 'NO', 'PL', 'PT', 'RO', 'RU', 'SM', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'UA', 'VA',
            // Asia (mainland)
            'AF', 'AM', 'AZ', 'BH', 'BD', 'BT', 'KH', 'CN', 'GE', 'IN', 'IR', 'IQ', 'IL', 'JO', 'KZ', 'KW', 'KG', 'LA', 'LB', 'MY',
            'MN', 'MM', 'NP', 'KP', 'KR', 'OM', 'PK', 'PS', 'QA', 'SA', 'SY', 'TJ', 'TH', 'TR', 'TM', 'AE', 'UZ', 'VN', 'YE', 'HK',
        ],
        'americas' => [
            'US', 'CA', 'MX', 'GT', 'BZ', 'SV', 'HN', 'NI', 'CR', 'PA', 'CO', 'VE', 'EC', 'PE', 'BO', 'BR', 'PY', 'UY', 'AR', 'CL',
            'GY', 'SR', 'GF',
        ],
    ];

    /**
     * EU and wider European countries served by the FedEx last mile in this specification.
     *
     * @var array<int, string>
     */
    private const EUROPE = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB', 'CH', 'NO', 'IS', 'LI', 'MC', 'AD', 'SM',
    ];

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public static function landmass(string $country): ?string
    {
        $country = strtoupper($country);
        foreach (self::LANDMASSES as $name => $countries) {
            if (in_array($country, $countries, true)) {
                return $name;
            }
        }

        return null;
    }

    public static function sameLandmass(string $a, string $b): bool
    {
        if (strtoupper($a) === strtoupper($b)) {
            return true;
        }
        $first = self::landmass($a);

        return $first !== null && $first === self::landmass($b);
    }

    public static function isEurope(string $country): bool
    {
        return in_array(strtoupper($country), self::EUROPE, true);
    }

    /**
     * Network region code for a destination country.
     */
    public static function networkRegion(string $country): string
    {
        $country = strtoupper($country);
        if ($country === 'US') {
            return 'us';
        }
        if (self::isEurope($country)) {
            return 'europe';
        }

        return 'global';
    }

    /**
     * Plain-text last-mile network label. Carrier names are text only, never logos (risk R1).
     */
    public static function networkLabel(string $country): string
    {
        return match (self::networkRegion($country)) {
            'us' => __('USPS and UPS last-mile delivery'),
            'europe' => __('FedEx last-mile delivery'),
            default => __(':brand air, sea and road network with local partners', ['brand' => config('platform.brand.name')]),
        };
    }

    public static function countryName(string $code, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = Locale::getDisplayRegion('-'.strtoupper($code), $locale);

        return $name !== '' && $name !== strtoupper($code) ? $name : strtoupper($code);
    }

    /**
     * ISO 3166-1 alpha-2 codes with localized names, sorted by name.
     *
     * @return array<string, string>
     */
    public static function countries(?string $locale = null): array
    {
        $codes = [
            'AD', 'AE', 'AF', 'AG', 'AL', 'AM', 'AO', 'AR', 'AT', 'AU', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ',
            'BO', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ', 'CA', 'CD', 'CF', 'CG', 'CH', 'CI', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV',
            'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FR', 'GA', 'GB', 'GD',
            'GE', 'GH', 'GM', 'GN', 'GQ', 'GR', 'GT', 'GW', 'GY', 'HK', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IN', 'IQ', 'IS',
            'IT', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KM', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU',
            'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MG', 'MK', 'ML', 'MM', 'MN', 'MR', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
            'NE', 'NG', 'NI', 'NL', 'NO', 'NP', 'NZ', 'OM', 'PA', 'PE', 'PG', 'PH', 'PK', 'PL', 'PT', 'PY', 'QA', 'RO', 'RS', 'RU',
            'RW', 'SA', 'SC', 'SD', 'SE', 'SG', 'SI', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SZ', 'TD', 'TG', 'TH',
            'TJ', 'TM', 'TN', 'TR', 'TT', 'TW', 'TZ', 'UA', 'UG', 'US', 'UY', 'UZ', 'VE', 'VN', 'YE', 'ZA', 'ZM', 'ZW',
        ];

        $countries = [];
        foreach ($codes as $code) {
            $countries[$code] = self::countryName($code, $locale);
        }
        asort($countries, SORT_LOCALE_STRING);

        return $countries;
    }

    public static function isValidCountry(string $code): bool
    {
        return array_key_exists(strtoupper($code), self::countries('en'));
    }
}
