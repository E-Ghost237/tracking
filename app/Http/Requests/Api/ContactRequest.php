<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().\-]{6,30}$/'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'tracking_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'captcha_id' => ['nullable', 'string', 'max:64'],
            'captcha_answer' => ['required', 'string', 'max:2048'],
            'website' => ['prohibited'],
        ];
    }
}
