<?php

namespace App\Http\Requests\Api;

use App\Support\Geo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates one booking wizard step (section 4.4) before it is saved to the draft (FR-30).
 */
class DraftStepRequest extends FormRequest
{
    public const STEPS = ['route' => 1, 'packages' => 2, 'service' => 3, 'parties' => 4, 'review' => 5];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $step = (string) $this->input('step');
        $rules = ['step' => ['required', Rule::in(array_keys(self::STEPS))], 'data' => ['required', 'array']];

        return $rules + match ($step) {
            'route' => [
                ...$this->addressRules('data.origin'),
                ...$this->addressRules('data.destination'),
            ],
            'packages' => [
                'data.packages' => ['required', 'array', 'min:1', 'max:20'],
                'data.packages.*.description' => ['required', 'string', 'min:2', 'max:200'],
                'data.packages.*.weight_kg' => ['required', 'numeric', 'min:0.1', 'max:3000'],
                'data.packages.*.length_cm' => ['required', 'numeric', 'min:1', 'max:600'],
                'data.packages.*.width_cm' => ['required', 'numeric', 'min:1', 'max:600'],
                'data.packages.*.height_cm' => ['required', 'numeric', 'min:1', 'max:600'],
                'data.packages.*.value' => ['required', 'numeric', 'min:0', 'max:100000'],
                'data.packages.*.category' => ['required', 'string', Rule::in(array_keys(config('platform.package_categories')))],
            ],
            'service' => [
                'data.mode' => ['required', Rule::in(['air', 'sea', 'road', 'express'])],
                'data.insurance' => ['required', 'boolean'],
            ],
            'parties' => [
                ...$this->partyRules('data.sender'),
                ...$this->partyRules('data.recipient'),
                'data.customs' => ['nullable', 'array'],
                'data.customs.contents' => ['nullable', 'string', 'max:300'],
                'data.customs.hs_code' => ['nullable', 'string', 'regex:/^[0-9.]{4,14}$/'],
                'data.customs.reason' => ['nullable', Rule::in(['gift', 'sale', 'personal', 'documents', 'sample', 'return'])],
            ],
            'review' => [
                'data.accepted_terms' => ['accepted'],
            ],
            default => [],
        };
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('step') !== 'packages') {
                    return;
                }
                $categories = config('platform.package_categories');
                foreach ((array) $this->input('data.packages', []) as $index => $package) {
                    $category = $package['category'] ?? null;
                    if (is_string($category) && ($categories[$category]['prohibited'] ?? false)) {
                        $validator->errors()->add("data.packages.$index.category", __('We cannot carry this category of goods. See the prohibited items list.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array'],
            $prefix.'.line1' => ['required', 'string', 'max:200'],
            $prefix.'.line2' => ['nullable', 'string', 'max:200'],
            $prefix.'.city' => ['required', 'string', 'max:120'],
            $prefix.'.region' => ['nullable', 'string', 'max:120'],
            $prefix.'.postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]*$/'],
            $prefix.'.country' => ['required', 'string', 'size:2', fn ($a, $v, $fail) => is_string($v) && Geo::isValidCountry($v) ? null : $fail(__('Choose a valid country.'))],
            $prefix.'.lat' => ['required', 'numeric', 'between:-90,90'],
            $prefix.'.lon' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partyRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array'],
            $prefix.'.name' => ['required', 'string', 'min:2', 'max:120'],
            $prefix.'.company' => ['nullable', 'string', 'max:120'],
            $prefix.'.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            $prefix.'.email' => ['nullable', 'email:rfc', 'max:190'],
        ];
    }

    /**
     * Only the validated fields of this step are kept; anything else is dropped.
     *
     * @return array<string, mixed>
     */
    public function stepData(): array
    {
        $data = $this->validated()['data'];

        return match ((string) $this->input('step')) {
            'route' => ['origin' => $this->onlyAddress($data['origin']), 'destination' => $this->onlyAddress($data['destination'])],
            'packages' => array_values(array_map(fn (array $p) => [
                'description' => trim($p['description']),
                'weight_kg' => round((float) $p['weight_kg'], 2),
                'length_cm' => round((float) $p['length_cm'], 1),
                'width_cm' => round((float) $p['width_cm'], 1),
                'height_cm' => round((float) $p['height_cm'], 1),
                'value' => round((float) $p['value'], 2),
                'category' => $p['category'],
            ], $data['packages'])),
            'service' => ['mode' => $data['mode'], 'insurance' => (bool) $data['insurance']],
            'parties' => [
                'sender' => array_intersect_key($data['sender'], array_flip(['name', 'company', 'phone', 'email'])),
                'recipient' => array_intersect_key($data['recipient'], array_flip(['name', 'company', 'phone', 'email'])),
                'customs' => isset($data['customs']) ? array_intersect_key($data['customs'], array_flip(['contents', 'hs_code', 'reason'])) : null,
            ],
            'review' => ['accepted_terms' => true],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function onlyAddress(array $address): array
    {
        $clean = array_intersect_key($address, array_flip(['line1', 'line2', 'city', 'region', 'postal_code', 'country', 'lat', 'lon']));
        $clean['country'] = strtoupper($clean['country']);
        $clean['lat'] = round((float) $clean['lat'], 6);
        $clean['lon'] = round((float) $clean['lon'], 6);

        return $clean;
    }
}
