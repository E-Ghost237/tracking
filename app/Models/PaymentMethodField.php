<?php

namespace App\Models;

use App\Casts\SecureEncrypted;
use App\Observers\PaymentMethodFieldObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PaymentMethodFieldObserver::class)]
class PaymentMethodField extends Model
{
    public const TYPES = ['text' => 'Text', 'copy' => 'Copy to clipboard', 'qr' => 'QR image', 'link' => 'Link', 'note' => 'Note'];

    protected $fillable = ['payment_method_id', 'label_en', 'label_fr', 'value', 'pending_value', 'pending_effective_at', 'type', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => SecureEncrypted::class,
            'pending_value' => SecureEncrypted::class,
            'pending_effective_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'fr' ? ($this->label_fr ?: $this->label_en) : $this->label_en;
    }
}
