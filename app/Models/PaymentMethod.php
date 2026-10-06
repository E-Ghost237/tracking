<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Admin-configured manual payment method (FR-50). Account details live in the
 * encrypted payment_method_fields rows and are only revealed after selection (FR-54).
 */
class PaymentMethod extends Model
{
    use Auditable, SoftDeletes;

    public const KINDS = [
        'cashapp' => 'Cash App',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'chime' => 'Chime',
        'apple_pay' => 'Apple Pay (manual transfer)',
        'google_pay' => 'Google Pay (manual transfer)',
        'gift_card' => 'Gift card',
        'iban' => 'IBAN transfer',
        'paypal' => 'PayPal',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'slug', 'name', 'kind', 'logo_file_id', 'is_enabled', 'sort_order', 'currency', 'min_amount', 'max_amount',
        'fee_percent', 'fee_fixed', 'countries', 'requires_transaction_id', 'proof_required', 'risk_level',
        'expiry_hours', 'instructions_en', 'instructions_fr', 'gift_card_rules',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'fee_percent' => 'float',
            'fee_fixed' => 'integer',
            'countries' => 'array',
            'requires_transaction_id' => 'boolean',
            'proof_required' => 'boolean',
            'gift_card_rules' => 'array',
        ];
    }

    /**
     * @return HasMany<PaymentMethodField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(PaymentMethodField::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id');
    }

    public function isGiftCard(): bool
    {
        return $this->kind === 'gift_card';
    }

    public function instructions(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'fr' ? ($this->instructions_fr ?: $this->instructions_en) : $this->instructions_en;
    }

    /**
     * Fee in USD minor units for a given order total (BR-04).
     */
    public function feeFor(int $amount): int
    {
        return (int) round($amount * ($this->fee_percent / 100)) + $this->fee_fixed;
    }

    /**
     * Whether this method may be used for an order of this amount and country (FR-55).
     */
    public function allows(int $amount, ?string $country): bool
    {
        if (! $this->is_enabled) {
            return false;
        }
        if ($amount < $this->min_amount) {
            return false;
        }
        if ($this->max_amount !== null && $this->max_amount > 0 && $amount > $this->max_amount) {
            return false;
        }
        if (! empty($this->countries) && $country !== null && ! in_array(strtoupper($country), $this->countries, true)) {
            return false;
        }

        return true;
    }
}
