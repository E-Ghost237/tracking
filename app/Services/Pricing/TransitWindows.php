<?php

namespace App\Services\Pricing;

use App\Models\RateCard;
use App\Models\RateLine;
use Illuminate\Support\Facades\Cache;

/**
 * Transit windows published on the active rate card, grouped by transport mode.
 *
 * Every public page that shows a typical transit window reads it from here, so a
 * rate card change updates the marketing pages with the pricing pages and no
 * template ever carries a hard-coded number (R6: no invented figures).
 */
class TransitWindows
{
    /**
     * @return array<string, array{min_days: int, max_days: int, lines: int}>
     */
    public function forModes(): array
    {
        $locale = app()->getLocale();

        return Cache::remember('transit-windows:'.$locale, now()->addMinutes(10), function (): array {
            $card = RateCard::current();
            if ($card === null) {
                return [];
            }

            return RateLine::query()
                ->where('rate_card_id', $card->id)
                ->groupBy('mode')
                ->selectRaw('mode, min(transit_min_days) as min_days, max(transit_max_days) as max_days, count(*) as lines')
                ->get()
                ->mapWithKeys(fn (RateLine $row): array => [$row->mode => [
                    'min_days' => (int) $row->min_days,
                    'max_days' => (int) $row->max_days,
                    'lines' => (int) $row->lines,
                ]])
                ->all();
        });
    }

    /**
     * @return array{min_days: int, max_days: int, lines: int}|null
     */
    public function forMode(string $mode): ?array
    {
        return $this->forModes()[$mode] ?? null;
    }

    /**
     * Ready-to-print window per mode, e.g. ['air' => '2–8 days'].
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(
            fn (array $row): string => $row['min_days'].'–'.$row['max_days'].' '.__('days'),
            $this->forModes(),
        );
    }

    /**
     * "4–8 days", or a route-level confirmation note when nothing is published
     * for that mode — never a made-up number.
     */
    public function label(string $mode): string
    {
        $row = $this->forMode($mode);

        return $row === null
            ? __('Confirmed per route')
            : $row['min_days'].'–'.$row['max_days'].' '.__('days');
    }

    /**
     * The shortest and longest window across all published lanes, for pages that
     * talk about the service range as a whole.
     *
     * @return array{min_days: int, max_days: int}|null
     */
    public function overall(): ?array
    {
        $rows = $this->forModes();
        if ($rows === []) {
            return null;
        }

        return [
            'min_days' => min(array_column($rows, 'min_days')),
            'max_days' => max(array_column($rows, 'max_days')),
        ];
    }
}
