<?php

namespace App\Http\Requests\Api;

use App\Support\Geo;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:200'],
            'line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]*$/'],
            'country' => ['required', 'string', 'size:2', fn ($a, $v, $fail) => is_string($v) && Geo::isValidCountry($v) ? null : $fail(__('Choose a valid country.'))],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default_sender' => ['nullable', 'boolean'],
            'is_default_recipient' => ['nullable', 'boolean'],
        ];
    }
}
