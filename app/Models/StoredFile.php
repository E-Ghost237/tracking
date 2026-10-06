<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reference to a file in (private) object storage. Never served directly.
 */
class StoredFile extends Model
{
    use HasPublicId, SoftDeletes;

    /**
     * In-memory defaults matching the column defaults, so new instances are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['scan_status' => 'pending', 'is_private' => true];

    protected $table = 'files';

    protected $fillable = [
        'disk', 'path', 'mime', 'size', 'sha256', 'owner_id', 'is_private', 'purpose',
        'original_name', 'thumbnail_path', 'scan_status', 'meta',
    ];

    protected $hidden = ['id', 'disk', 'path', 'thumbnail_path', 'owner_id', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'meta' => 'array',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
