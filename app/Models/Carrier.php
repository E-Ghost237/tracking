<?php

namespace App\Models;

use App\Casts\SecureEncryptedJson;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'number_patterns', 'tracking_url_template', 'region', 'api_config', 'is_own', 'is_active', 'sort_order'];

    protected $hidden = ['api_config'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number_patterns' => 'array',
            'api_config' => SecureEncryptedJson::class,
            'is_own' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether a normalised tracking number matches one of this carrier's formats (FR-11).
     */
    public function matches(string $number): bool
    {
        foreach ($this->number_patterns ?? [] as $pattern) {
            $regex = '/^(?:'.str_replace('/', '\/', $pattern).')$/';
            if (@preg_match($regex, $number) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Number formats for the client-side detection hint (FR-04). The server stays authoritative.
     *
     * @return array<int, array{name: string, patterns: array<int, string>}>
     */
    public static function formatsForClient(): array
    {
        return static::query()->where('is_active', true)->orderBy('sort_order')->get(['name', 'number_patterns'])
            ->map(fn (self $carrier) => ['name' => $carrier->name, 'patterns' => $carrier->number_patterns ?? []])
            ->values()->all();
    }

    public function trackingUrl(string $number): ?string
    {
        if (blank($this->tracking_url_template)) {
            return null;
        }

        return str_replace('{number}', rawurlencode($number), $this->tracking_url_template);
    }
}
