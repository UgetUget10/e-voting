<?php

namespace App\Models;

use App\Services\ResultService;
use Database\Factories\VoteCorrectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual correction to the vote total of one candidate in one field. It stores the change from the count at
 * the time, so ballots and picks are never touched. Every total adds the corrections to the counted picks.
 */
class VoteCorrection extends Model
{
    /** @use HasFactory<VoteCorrectionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['candidate_id', 'field_id', 'user_id', 'amount', 'reason'];

    /**
     * Any correction moves the standings, so the cached results are dropped.
     */
    protected static function booted(): void
    {
        static::saved(fn () => ResultService::forgetCache());
        static::deleted(fn () => ResultService::forgetCache());
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
