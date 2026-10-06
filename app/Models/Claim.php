<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    use Auditable, HasPublicId;

    public const TYPES = ['lost' => 'Lost', 'damaged' => 'Damaged', 'delayed' => 'Delayed'];

    public const STATUSES = ['open' => 'Open', 'under_review' => 'Under review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid'];

    protected $fillable = ['type', 'description', 'amount_claimed', 'currency'];

    protected $hidden = ['id', 'shipment_id', 'user_id', 'decided_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_claimed' => 'integer',
            'amount_approved' => 'integer',
            'attachments' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return HasMany<ClaimLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ClaimLog::class)->orderBy('created_at');
    }
}
