<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Proof of payment upload (section 5.5). Content type is checked again from the bytes
 * by FileStorageService; these rules are the first gate.
 */
class ProofUploadRequest extends FormRequest
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
        $maxKb = (int) config('platform.upload.proof_max_kb', 8192);

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('platform.upload.proof_max_files', 3)],
            'files.*' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf'],
            'amount_paid' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'payer_name' => ['required', 'string', 'min:2', 'max:120'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'transaction_id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9 #._\-\/]+$/'],
            'note' => ['nullable', 'string', 'max:1000'],
            'gift_card' => ['nullable', 'array'],
            'gift_card.brand' => ['required_with:gift_card', 'string', 'max:60'],
            'gift_card.code' => ['required_with:gift_card', 'string', 'max:80'],
            'gift_card.pin' => ['nullable', 'string', 'max:20'],
            'gift_card.amount' => ['required_with:gift_card', 'numeric', 'min:1', 'max:10000'],
        ];
    }
}
