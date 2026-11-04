<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Field;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CandidateTimeline
{
    private const WIDTH = 600;

    private const HEIGHT = 140;

    private const COLORS = ['#ea580c', '#0f766e', '#4f46e5', '#be123c', '#ca8a04', '#525252'];

    /**
     * Cumulative votes per candidate of one field over time, drawn on one shared scale.
     * Only vote timestamps and candidate counts are read, never voter identity.
     *
     * @param  Collection<int, Candidate>  $candidates
     * @return array{series: list<array{name: string, color: string, line: string, votes: int, points: list<array{0: float, 1: int}>}>, startedAt: ?Carbon, span: int}
     */
    public function build(Field $field, Collection $candidates): array
    {
        $votes = Vote::query()
            ->where('field_id', $field->getKey())
            ->orderBy('created_at')
            ->get(['candidate_id', 'created_at']);

        if ($votes->isEmpty()) {
            return ['series' => [], 'startedAt' => null, 'span' => 0];
        }

        $startedAt = $votes->first()->created_at;
        $span = max(1, $startedAt->diffInSeconds(now()));
        $tally = [];
        $points = [];

        foreach ($votes as $vote) {
            $tally[$vote->candidate_id] = ($tally[$vote->candidate_id] ?? 0) + 1;
            $points[$vote->candidate_id][] = [
                round($startedAt->diffInSeconds($vote->created_at) / $span * self::WIDTH, 1),
                $tally[$vote->candidate_id],
            ];
        }

        $maximum = max($tally);

        $series = $candidates
            ->values()
            ->filter(fn ($candidate): bool => isset($tally[$candidate->getKey()]))
            ->map(function ($candidate, int $index) use ($points, $tally, $maximum): array {
                $line = array_map(
                    fn (array $point): string => $point[0].','.$this->yFor($point[1], $maximum),
                    $points[$candidate->getKey()],
                );
                $lastCount = $tally[$candidate->getKey()];

                return [
                    'name' => $candidate->name,
                    'color' => self::COLORS[$index % count(self::COLORS)],
                    'line' => implode(' ', [
                        '0,'.self::HEIGHT,
                        ...$line,
                        self::WIDTH.','.$this->yFor($lastCount, $maximum),
                    ]),
                    'votes' => $lastCount,
                    'points' => [[0.0, 0], ...$points[$candidate->getKey()]],
                ];
            })
            ->values()
            ->all();

        return ['series' => $series, 'startedAt' => $startedAt, 'span' => $span];
    }

    private function yFor(int $count, int $maximum): float
    {
        return round(self::HEIGHT - $count / $maximum * self::HEIGHT, 1);
    }
}
