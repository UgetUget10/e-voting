<?php

namespace App\Models;

use App\Services\ResultService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['ballot_id', 'field_id', 'candidate_id'];

    /**
     * Any vote change moves the standings, so the cached results are dropped.
     */
    protected static function booted(): void
    {
        static::saved(fn () => ResultService::forgetCache());
        static::deleted(fn () => ResultService::forgetCache());
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
