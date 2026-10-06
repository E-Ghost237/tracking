<?php

namespace App\Models;

use App\Enums\ProofStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentProof extends Model
{
    use HasPublicId;

    protected $fillable = [];

    protected $hidden = ['id', 'order_payment_id', 'order_id', 'user_id', 'duplicate_of'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProofStatus::class,
            'amount_paid' => 'integer',
            'paid_on' => 'date',
            'is_duplicate' => 'boolean',
            'duplicate_of' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'overdue_alerted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderPayment, $this>
     */
    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<StoredFile, $this>
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(StoredFile::class, 'payment_proof_files', 'payment_proof_id', 'file_id')->withPivot('sha256');
    }

    /**
     * @return HasMany<PaymentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PaymentReview::class)->orderBy('decided_at');
    }

    /**
     * @return HasOne<GiftCardSubmission, $this>
     */
    public function giftCard(): HasOne
    {
        return $this->hasOne(GiftCardSubmission::class);
    }

    public function ageInMinutes(): int
    {
        return (int) $this->submitted_at->diffInMinutes(now());
    }
}
