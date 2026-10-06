<?php

namespace App\Services\Pricing;

use App\Models\RateCard;
use App\Models\RateLine;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CSV import and export of rate cards (FR-125). Imports always create a new, inactive version.
 */
class RateCardCsv
{
    private const COLUMNS = ['zone_from', 'zone_to', 'mode', 'base_fee', 'weight_from_kg', 'weight_to_kg', 'price_per_kg', 'transit_min_days', 'transit_max_days'];

    private const MAX_ROWS = 5000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function export(RateCard $card): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::COLUMNS, escape: '');
        foreach ($card->lines()->orderBy('zone_from')->orderBy('zone_to')->orderBy('mode')->orderBy('weight_from_kg')->cursor() as $line) {
            fputcsv($out, array_map(fn ($c) => $this->safeCell((string) $line->{$c}), self::COLUMNS), escape: '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    public function import(string $path, string $name, ?User $user): RateCard
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), (array) fgetcsv($handle, escape: ''));
        if ($header !== self::COLUMNS) {
            throw new InvalidArgumentException('The header must be: '.implode(',', self::COLUMNS));
        }

        $zones = Zone::query()->pluck('code')->push('ROW')->all();
        $rows = [];
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $lineNumber++;
            if ($row === [null] || $row === []) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = 'Too many rows (max '.self::MAX_ROWS.').';
                break;
            }
            if (count($row) !== count(self::COLUMNS)) {
                $errors[] = "Line $lineNumber: wrong number of columns.";

                continue;
            }

            $data = array_combine(self::COLUMNS, array_map('trim', $row));
            $lineErrors = [];
            if (! in_array($data['zone_from'], $zones, true) || ! in_array($data['zone_to'], $zones, true)) {
                $lineErrors[] = 'unknown zone';
            }
            if (! in_array($data['mode'], ['air', 'sea', 'road'], true)) {
                $lineErrors[] = 'mode must be air, sea or road';
            }
            foreach (['base_fee', 'price_per_kg', 'transit_min_days', 'transit_max_days'] as $int) {
                if (! ctype_digit($data[$int])) {
                    $lineErrors[] = "$int must be a whole number";
                }
            }
            foreach (['weight_from_kg', 'weight_to_kg'] as $num) {
                if (! is_numeric($data[$num]) || (float) $data[$num] < 0) {
                    $lineErrors[] = "$num must be a positive number";
                }
            }
            if ($lineErrors === [] && (float) $data['weight_to_kg'] <= (float) $data['weight_from_kg']) {
                $lineErrors[] = 'weight_to_kg must be greater than weight_from_kg';
            }

            if ($lineErrors !== []) {
                $errors[] = "Line $lineNumber: ".implode(', ', $lineErrors);

                continue;
            }

            $rows[] = $data;
        }
        fclose($handle);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", array_slice($errors, 0, 20)));
        }
        if ($rows === []) {
            throw new InvalidArgumentException('The file has no rate lines.');
        }

        return DB::transaction(function () use ($rows, $name, $user): RateCard {
            $card = RateCard::query()->create([
                'version' => (int) RateCard::query()->lockForUpdate()->max('version') + 1,
                'name' => mb_substr($name, 0, 120),
                'is_active' => false,
                'created_by' => $user?->id,
            ]);

            foreach (array_chunk($rows, 500) as $chunk) {
                RateLine::query()->insert(array_map(fn (array $r) => $r + ['rate_card_id' => $card->id, 'created_at' => now(), 'updated_at' => now()], $chunk));
            }

            $this->audit->log('rate_card.imported', $card, null, ['lines' => count($rows)], $user);

            return $card;
        });
    }

    /**
     * Neutralises spreadsheet formula injection in exported cells.
     */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
