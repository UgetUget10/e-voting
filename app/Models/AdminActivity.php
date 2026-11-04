<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One entry in the admin activity log. Entries are a record of what happened, so they cannot be changed or removed.
 */
class AdminActivity extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'description', 'category', 'sensitive', 'day', 'changes', 'subject_type', 'subject_id'];

    protected static function booted(): void
    {
        static::creating(function (AdminActivity $activity): void {
            $activity->day ??= now('Asia/Jakarta')->toDateString();
        });

        static::updating(function (): void {
            throw new LogicException('Catatan aktivitas tidak dapat diubah.');
        });

        static::deleting(function (): void {
            throw new LogicException('Catatan aktivitas tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'changes' => 'array',
            'sensitive' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record this entry is about, such as one ballot, so its history can be shown with the record.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
