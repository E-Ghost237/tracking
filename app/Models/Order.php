<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasPublicId, SoftDeletes;

    /**
     * In-memory defaults matching the column defaults, so new instances are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['fee' => 0, 'amount_paid' => 0, 'credit' => 0, 'rejected_attempts' => 0, 'is_escalated' => false];

    /**
     * Money and status columns are never mass assignable from requests; services set them.
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected $hidden = ['id', 'user_id', 'quote_id', 'created_by', 'deleted_at', 'reminders_sent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'fee' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'credit' => 'integer',
            'is_escalated' => 'boolean',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'reminders_sent' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return HasOne<Shipment, $this>
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<OrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * The currently selected (not superseded) payment attempt.
     *
     * @return HasOne<OrderPayment, $this>
     */
    public function currentPayment(): HasOne
    {
        return $this->hasOne(OrderPayment::class)->whereNull('superseded_at')->latestOfMany('selected_at');
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Balance still owed in the order currency.
     */
    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }
}
