<?php

namespace App\Models;

use App\Casts\SecureEncryptedJson;
use App\Enums\OrderPaymentStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payment attempt on an order. Keeps an encrypted snapshot of the details shown (FR-53).
 */
class OrderPayment extends Model
{
    use HasPublicId;

    protected $fillable = [];

    protected $hidden = ['id', 'order_id', 'payment_method_id', 'details_snapshot'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderPaymentStatus::class,
            'details_snapshot' => SecureEncryptedJson::class,
            'exchange_rate' => 'float',
            'fee' => 'integer',
            'amount_expected' => 'integer',
            'amount_received' => 'integer',
            'selected_at' => 'datetime',
            'superseded_at' => 'datetime',
            'rate_locked_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id')->withTrashed();
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->amount_expected - $this->amount_received);
    }
}
