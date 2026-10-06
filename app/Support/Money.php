<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money is stored as integer minor units plus a currency code (section 8).
 */
final class Money
{
    public static function format(int $minor, string $currency = 'USD', ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $formatter = new NumberFormatter($locale === 'fr' ? 'fr_FR' : 'en_US', NumberFormatter::CURRENCY);
        $digits = self::decimals($currency);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $digits);

        return (string) $formatter->formatCurrency($minor / (10 ** $digits), strtoupper($currency));
    }

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), ['XAF', 'XOF', 'JPY'], true) ? 0 : 2;
    }

    /**
     * Converts USD minor units into another currency's minor units at a given rate.
     */
    public static function convert(int $usdMinor, string $currency, float $rate): int
    {
        if (strtoupper($currency) === 'USD') {
            return $usdMinor;
        }

        $major = ($usdMinor / 100) * $rate;

        return (int) round($major * (10 ** self::decimals($currency)));
    }

    public static function toMajor(int $minor, string $currency = 'USD'): float
    {
        return $minor / (10 ** self::decimals($currency));
    }

    public static function fromMajor(float|int|string $major, string $currency = 'USD'): int
    {
        return (int) round(((float) $major) * (10 ** self::decimals($currency)));
    }
}
