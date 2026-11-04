<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Vote;

class BallotIntegrityCheck
{
    /**
     * Count the problems that would make a result untrustworthy: ballots without picks, picks that point at
     * missing data, ballots without a voter, and ballots sharing a receipt code.
     *
     * @return array{withoutPicks: int, brokenPicks: int, orphanedBallots: int, duplicateReceipts: int, total: int}
     */
    public function run(): array
    {
        $withoutPicks = Ballot::query()->doesntHave('votes')->count();
        $brokenPicks = Vote::query()->where(fn ($query) => $query->doesntHave('field')->orWhereDoesntHave('candidate'))->count();
        $orphanedBallots = Ballot::query()->doesntHave('user')->count();
        $duplicateReceipts = Ballot::query()
            ->select('receipt_code')
            ->whereNotNull('receipt_code')
            ->groupBy('receipt_code')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        return [
            'withoutPicks' => $withoutPicks,
            'brokenPicks' => $brokenPicks,
            'orphanedBallots' => $orphanedBallots,
            'duplicateReceipts' => $duplicateReceipts,
            'total' => $withoutPicks + $brokenPicks + $orphanedBallots + $duplicateReceipts,
        ];
    }
}
