<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Vote;
use App\Models\VoteCorrection;
use Illuminate\Support\Facades\DB;

class BallotReset
{
    /**
     * Remove every submitted ballot, its picks and any manual vote corrections, returning how many ballots were removed.
     * Voters, fields, candidates and the election are kept. Mass deletes skip model events,
     * so the per-ballot guard on Ballot does not block this deliberate reset.
     */
    public function reset(): int
    {
        return DB::transaction(function (): int {
            $ballotCount = Ballot::query()->count();

            Vote::query()->delete();
            VoteCorrection::query()->delete();
            Ballot::query()->delete();

            ResultService::forgetCache();

            return $ballotCount;
        });
    }
}
