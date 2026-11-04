<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Field;
use App\Models\Vote;
use App\Models\VoteCorrection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ResultService
{
    /**
     * Standings are shared by the admin page, the voter page, exports and polling. They are kept for a few
     * seconds as a fallback, and cleared whenever a vote, candidate or field changes.
     */
    public const CACHE_SECONDS = 5;

    public const CACHE_KEY = 'results.tallies';

    /**
     * Winners for each field, in field order. Per field the top two vote
     * counts advance. Candidates tied with the second-place count all advance,
     * so a tie never forces a re-vote. Candidates without any vote never advance.
     *
     * Only the vote totals are cached. The fields and candidates are read fresh each time, because
     * cached Eloquent models cannot be restored reliably from the cache.
     *
     * @return Collection<int, array{field: Field, candidates: Collection, winners: Collection}>
     */
    public function results(): Collection
    {
        $tallies = $this->tallies();
        $candidates = Candidate::query()->ordered()->get();

        return Field::query()
            ->ordered()
            ->get()
            ->map(function (Field $field) use ($candidates, $tallies) {
                $fieldTallies = $tallies[$field->getKey()] ?? [];
                $fieldCandidates = $candidates
                    ->map(fn (Candidate $candidate): Candidate => (clone $candidate)->setAttribute('votes_count', $fieldTallies[$candidate->getKey()] ?? 0))
                    ->values();

                return [
                    'field' => $field,
                    'candidates' => $fieldCandidates,
                    'winners' => $this->winners($fieldCandidates),
                ];
            });
    }

    /**
     * Drops the cached totals so the next read counts again. Called by the models whenever a vote,
     * candidate or field changes, and by any bulk delete that skips model events.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Vote totals as plain numbers: field id, then candidate id, then the count, with corrections added.
     *
     * @return array<string, array<string, int>>
     */
    private function tallies(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $tallies = [];

            Vote::query()
                ->selectRaw('field_id, candidate_id, count(*) as total')
                ->groupBy('field_id', 'candidate_id')
                ->get()
                ->each(function (Vote $row) use (&$tallies): void {
                    $tallies[$row->field_id][$row->candidate_id] = (int) $row->total;
                });

            VoteCorrection::query()
                ->selectRaw('field_id, candidate_id, sum(amount) as total')
                ->groupBy('field_id', 'candidate_id')
                ->get()
                ->each(function (VoteCorrection $row) use (&$tallies): void {
                    $tallies[$row->field_id][$row->candidate_id] = ($tallies[$row->field_id][$row->candidate_id] ?? 0) + (int) $row->total;
                });

            return $tallies;
        });
    }

    /**
     * A field is tied when its first and second candidates have the same non-zero vote count.
     *
     * @param  Collection<int, Candidate>  $candidates  Each with a votes_count attribute.
     */
    public function isTied(Collection $candidates): bool
    {
        $ranked = $candidates->sortByDesc('votes_count')->values();

        return $ranked->count() > 1
            && $ranked[0]->votes_count > 0
            && $ranked[0]->votes_count === $ranked[1]->votes_count;
    }

    /**
     * Candidates of one field with their vote counts within that field only, corrections included.
     */
    public function candidatesFor(Field $field): Collection
    {
        $corrections = VoteCorrection::query()
            ->selectRaw('candidate_id, sum(amount) as total')
            ->where('field_id', $field->id)
            ->groupBy('candidate_id')
            ->pluck('total', 'candidate_id');

        return Candidate::query()
            ->withCount(['votes as votes_count' => fn ($query) => $query->where('votes.field_id', $field->id)])
            ->ordered()
            ->get()
            ->map(fn (Candidate $candidate): Candidate => $candidate->setAttribute(
                'votes_count',
                $candidate->votes_count + (int) ($corrections[$candidate->getKey()] ?? 0),
            ));
    }

    /**
     * @param  Collection<int, Candidate>  $candidates  Each with a votes_count attribute.
     */
    public function winners(Collection $candidates): Collection
    {
        $ranked = $candidates->where('votes_count', '>', 0)->sortByDesc('votes_count')->values();

        if ($ranked->count() <= 2) {
            return $ranked;
        }

        $cutoff = $ranked[1]->votes_count;

        return $ranked->filter(fn ($candidate) => $candidate->votes_count >= $cutoff)->values();
    }
}
