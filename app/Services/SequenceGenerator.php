<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential numbers (invoices, receipts, tracking numbers). Rows are locked
 * for the duration of the surrounding transaction so numbers are never reused (FR-104).
 */
class SequenceGenerator
{
    public function next(string $name): int
    {
        return DB::transaction(function () use ($name): int {
            Sequence::query()->insertOrIgnore(['name' => $name, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);

            $sequence = Sequence::query()->where('name', $name)->lockForUpdate()->firstOrFail();
            $sequence->value = $sequence->value + 1;
            $sequence->save();

            return (int) $sequence->value;
        });
    }

    public function invoiceNumber(): string
    {
        $year = now()->format('Y');

        return sprintf('INV-%s-%06d', $year, $this->next('invoice-'.$year));
    }

    public function creditNoteNumber(): string
    {
        $year = now()->format('Y');

        return sprintf('CN-%s-%06d', $year, $this->next('credit-note-'.$year));
    }

    public function receiptNumber(): string
    {
        $year = now()->format('Y');

        return sprintf('RCPT-%s-%06d', $year, $this->next('receipt-'.$year));
    }

    public function orderNumber(): string
    {
        $year = now()->format('Y');

        return sprintf('ORD-%s-%06d', $year, $this->next('order-'.$year));
    }

    /**
     * Own-freight tracking number CV-AIR-123456: a 5-digit (or longer) sequence plus a Luhn check digit (FR-32).
     */
    public function trackingNumber(string $mode): string
    {
        $prefix = match ($mode) {
            'sea' => 'SEA',
            'road' => 'RD',
            default => 'AIR',
        };

        $sequence = str_pad((string) ($this->next('tracking-'.$prefix) + 10000), 5, '0', STR_PAD_LEFT);

        return sprintf('CV-%s-%s%d', $prefix, $sequence, self::luhnCheckDigit($sequence));
    }

    public static function luhnCheckDigit(string $digits): int
    {
        $sum = 0;
        $double = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = ! $double;
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function isValidOwnTrackingNumber(string $number): bool
    {
        if (preg_match('/^CV-(AIR|SEA|RD)-(\d{5,})(\d)$/', $number, $m) !== 1) {
            return false;
        }

        return self::luhnCheckDigit($m[2]) === (int) $m[3];
    }
}
