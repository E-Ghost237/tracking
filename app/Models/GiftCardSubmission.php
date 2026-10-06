<?php

namespace App\Models;

use App\Casts\SecureEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gift card details: code and PIN encrypted at rest, masked everywhere (FR-91).
 */
class GiftCardSubmission extends Model
{
    protected $fillable = [];

    protected $hidden = ['code', 'pin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => SecureEncrypted::class,
            'pin' => SecureEncrypted::class,
            'amount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentProof, $this>
     */
    public function proof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class, 'payment_proof_id');
    }

    public function maskedCode(): string
    {
        return '•••• •••• '.$this->code_last4;
    }
}
