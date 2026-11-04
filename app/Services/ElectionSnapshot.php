<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\User;

class ElectionSnapshot
{
    /**
     * Freeze the current standings so they stay readable after the votes are reset for a new election.
     *
     * @return array{voters: int, ballots: int, turnout: float, fields: list<array{name: string, candidates: list<array{name: string, votes: int, advances: bool}>}>}
     */
    public function build(): array
    {
        $voters = User::query()->where('is_admin', false)->count();
        $ballots = Ballot::query()->count();

        return [
            'voters' => $voters,
            'ballots' => $ballots,
            'turnout' => $voters > 0 ? round($ballots / $voters * 100, 1) : 0.0,
            'fields' => app(ResultService::class)->results()
                ->map(fn (array $result): array => [
                    'name' => $result['field']->name,
                    'candidates' => $result['candidates']->sortByDesc('votes_count')->values()
                        ->map(fn ($candidate): array => [
                            'name' => $candidate->name,
                            'votes' => (int) $candidate->votes_count,
                            'advances' => $result['winners']->contains('id', $candidate->id),
                        ])->all(),
                ])->all(),
        ];
    }
}
