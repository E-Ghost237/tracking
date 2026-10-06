<?php

namespace App\Services\Tracking;

use App\Models\Carrier;
use Illuminate\Support\Collection;

/**
 * Normalises tracking input and detects the carrier from number formats stored in the carriers table (FR-10, FR-11).
 */
class CarrierDetector
{
    /**
     * @var Collection<int, Carrier>|null
     */
    private ?Collection $carriers = null;

    /**
     * Splits raw input into at most $max normalised numbers.
     *
     * @return array<int, string>
     */
    public function parse(string $input, int $max = 20): array
    {
        $input = mb_substr($input, 0, 2000);
        $numbers = [];

        foreach (preg_split('/[,;\r\n]+/', $input) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            // A chunk with spaces may be one number written in groups ("9400 1000 ...") or several numbers.
            $joined = $this->canonical($chunk);
            if ($this->detect($joined) !== null || ! str_contains($chunk, ' ')) {
                $numbers[] = $joined;
            } else {
                foreach (preg_split('/\s+/', $chunk) ?: [] as $part) {
                    $numbers[] = $this->canonical($part);
                }
            }
        }

        $numbers = array_values(array_unique(array_filter($numbers, fn (string $n) => $n !== '' && strlen($n) <= 40)));

        return array_slice($numbers, 0, $max);
    }

    /**
     * Uppercases and strips spaces and hyphens; own-freight numbers get their display hyphens back.
     */
    public function canonical(string $number): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $number));

        if (preg_match('/^CV(AIR|SEA|RD)(\d{6,})$/', $clean, $m) === 1) {
            return 'CV-'.$m[1].'-'.$m[2];
        }

        return $clean;
    }

    public function detect(string $number): ?Carrier
    {
        $compact = str_replace('-', '', $number);

        foreach ($this->carriers() as $carrier) {
            if ($carrier->matches($compact)) {
                return $carrier;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Carrier>
     */
    private function carriers(): Collection
    {
        return $this->carriers ??= Carrier::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
