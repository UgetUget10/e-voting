<?php

namespace App\Models;

use Database\Factories\BallotNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal admin notes on a submitted ballot. They never change the ballot or its picks, and they are kept
 * for as long as the ballot is.
 */
class BallotNote extends Model
{
    /** @use HasFactory<BallotNoteFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['ballot_id', 'user_id', 'body'];

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
