<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable settings (FR-129) with config defaults and a cached lookup.
 */
class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = Cache::remember(self::CACHE_KEY, 300, fn () => Setting::query()->pluck('value', 'key')->all());

        return $this->loaded = array_merge(config('platform.settings', []), $stored);
    }

    /**
     * @return array{before: mixed, after: mixed}
     */
    public function set(string $key, mixed $value): array
    {
        $before = $this->get($key);
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();

        return ['before' => $before, 'after' => $value];
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function exchangeRate(string $currency): float
    {
        $rates = (array) $this->get('exchange_rates', []);

        return (float) ($rates[strtoupper($currency)] ?? 0);
    }
}
