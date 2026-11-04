<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Field;
use Illuminate\Support\Collection;

/**
 * Where each field stands: its status, its leader, and its total. It reads the same tallies as the results,
 * so the table, the stats, the detail page and the exports always agree.
 */
class FieldStandings
{
    public const EMPTY = 'empty';

    public const TIED = 'tied';

    public const LEADING = 'leading';

    public function __construct(private ResultService $results) {}

    /**
     * @return Collection<int, array{field: Field, status: string, total: int, leaderShare: int, ranked: Collection<int, Candidate>, leader: ?Candidate, winners: Collection<int, Candidate>}>
     */
    public function all(): Collection
    {
        return once(fn (): Collection => $this->results->results()
            ->map(fn (array $result): array => $this->summarize($result['field'], $result['candidates'], $result['winners']))
            ->keyBy(fn (array $standing): string => $standing['field']->getKey()));
    }

    /**
     * @return array{field: Field, status: string, total: int, leaderShare: int, ranked: Collection<int, Candidate>, leader: ?Candidate, winners: Collection<int, Candidate>}
     */
    public function for(Field $field): array
    {
        return $this->all()->get($field->getKey())
            ?? $this->summarize($field, collect(), collect());
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::TIED => 'Seri',
            self::LEADING => 'Ada pemenang',
            default => 'Belum ada suara',
        };
    }

    /**
     * @param  Collection<int, Candidate>  $candidates  Each with a votes_count attribute.
     * @param  Collection<int, Candidate>  $winners
     * @return array{field: Field, status: string, total: int, leaderShare: int, ranked: Collection<int, Candidate>, leader: ?Candidate, winners: Collection<int, Candidate>}
     */
    private function summarize(Field $field, Collection $candidates, Collection $winners): array
    {
        $ranked = $candidates
            ->where('votes_count', '>', 0)
            ->sortBy([['votes_count', 'desc'], ['id', 'asc']])
            ->values();
        $total = (int) $candidates->sum('votes_count');
        $leader = $ranked->first();

        return [
            'field' => $field,
            'status' => match (true) {
                $ranked->isEmpty() => self::EMPTY,
                $this->results->isTied($candidates) => self::TIED,
                default => self::LEADING,
            },
            'total' => $total,
            'leaderShare' => $leader && $total > 0 ? (int) round($leader->votes_count / $total * 100) : 0,
            'ranked' => $ranked,
            'leader' => $leader,
            'winners' => $winners,
        ];
    }
}
