<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Field;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BallotTally
{
    /**
     * Count the picks made in the given ballots, per field, with each candidate's share of that field's picks.
     * Pass the table's filtered query so the tally matches the rows the admin is looking at. Candidates are
     * listed by votes, highest first.
     *
     * @return array{ballots: int, fields: list<array{name: string, picks: int, candidates: list<array{name: string, votes: int, share: float}>}>}
     */
    public function forBallots(Builder $ballots): array
    {
        // Clone the base query: toBase() hands back the table's own query, which must stay untouched.
        $ballotQuery = clone $ballots->toBase();
        $ballotIds = (clone $ballotQuery)->reorder()->select('ballots.id');

        $picksByField = Vote::query()
            ->whereIn('ballot_id', $ballotIds)
            ->selectRaw('field_id, candidate_id, count(*) as total')
            ->groupBy('field_id', 'candidate_id')
            ->get()
            ->groupBy(fn (Vote $vote): string => $vote->field_id)
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (Vote $vote): string => $vote->candidate_id));

        $candidateNames = Candidate::query()->orderBy('name')->pluck('name', 'id');

        return [
            'ballots' => (clone $ballotQuery)->reorder()->count(),
            'fields' => Field::query()->ordered()->get()->map(function (Field $field) use ($picksByField, $candidateNames): array {
                $picks = $picksByField->get($field->id, collect());
                $total = (int) $picks->sum('total');

                return [
                    'name' => $field->name,
                    'picks' => $total,
                    'candidates' => $candidateNames
                        ->map(fn (string $name, string $candidateId): array => [
                            'name' => $name,
                            'votes' => (int) ($picks->get($candidateId)?->total ?? 0),
                            'share' => $total > 0 ? round(((int) ($picks->get($candidateId)?->total ?? 0)) / $total * 100, 1) : 0.0,
                        ])
                        ->sortByDesc('votes')
                        ->values()
                        ->all(),
                ];
            })->values()->all(),
        ];
    }
}
