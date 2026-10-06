<?php

namespace App\Http\Requests\Api;

use App\Support\Geo;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quote inputs (FR-20). Bounds keep the calculator safe from absurd or hostile values.
 */
class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::placeRules('origin'),
            ...self::placeRules('destination'),
            'packages' => ['required', 'array', 'min:1', 'max:20'],
            'packages.*.weight_kg' => ['required', 'numeric', 'min:0.1', 'max:3000'],
            'packages.*.length_cm' => ['required', 'numeric', 'min:1', 'max:600'],
            'packages.*.width_cm' => ['required', 'numeric', 'min:1', 'max:600'],
            'packages.*.height_cm' => ['required', 'numeric', 'min:1', 'max:600'],
            'mode' => ['required', 'string', Rule::in(['air', 'sea', 'road', 'express'])],
            'declared_value' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'insurance' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function placeRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array:city,country,lat,lon,label,region,line1,line2,postal_code'],
            $prefix.'.city' => ['required', 'string', 'max:120'],
            $prefix.'.country' => ['required', 'string', 'size:2', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Geo::isValidCountry($value)) {
                    $fail(__('Choose a valid country.'));
                }
            }],
            $prefix.'.lat' => ['required', 'numeric', 'between:-90,90'],
            $prefix.'.lon' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteInput(): array
    {
        $data = $this->validated();
        $data['declared_value'] = Money::fromMajor($data['declared_value'] ?? 0);
        $data['insurance'] = (bool) ($data['insurance'] ?? false);
        $data['packages'] = array_map(fn (array $p) => [
            'weight_kg' => (float) $p['weight_kg'],
            'length_cm' => (float) $p['length_cm'],
            'width_cm' => (float) $p['width_cm'],
            'height_cm' => (float) $p['height_cm'],
        ], array_values($data['packages']));

        return $data;
    }
}
