<?php

namespace App\Services;

use App\Models\Ballot;
use Carbon\Carbon;

class ParticipationTrend
{
    /**
     * Cumulative share of registered voters who have cast a ballot, over time.
     * Only timestamps are read, so no voter identity leaves the server.
     *
     * @return array{line: string, area: string, total: int, percent: int, startedAt: ?Carbon, span: int, points: list<array{0: float, 1: int}>}
     */
    public function build(int $voterCount): array
    {
        $ballotTimes = Ballot::query()->orderBy('created_at')->pluck('created_at');
        $total = $ballotTimes->count();

        if ($total === 0) {
            return ['line' => '', 'area' => '', 'total' => 0, 'percent' => 0, 'startedAt' => null, 'span' => 0, 'points' => []];
        }

        $width = 600;
        $height = 120;
        $startedAt = $ballotTimes->first();
        $span = max(1, $startedAt->diffInSeconds(now()));
        $denominator = max(1, $voterCount);

        $points = $ballotTimes->values()->map(function (Carbon $castAt, int $index) use ($startedAt, $span, $width, $height, $denominator): array {
            $x = round($startedAt->diffInSeconds($castAt) / $span * $width, 1);
            $y = round($height - min(1, ($index + 1) / $denominator) * $height, 1);

            return ['x' => $x, 'y' => $y, 'count' => $index + 1];
        });

        $lastY = round($height - min(1, $total / $denominator) * $height, 1);
        $line = $points
            ->map(fn (array $point): string => "{$point['x']},{$point['y']}")
            ->prepend("0,{$height}")
            ->push("{$width},{$lastY}")
            ->implode(' ');

        return [
            'line' => $line,
            'area' => $line.' '."{$width},{$height} 0,{$height}",
            'total' => $total,
            'percent' => min(100, (int) round($total / $denominator * 100)),
            'startedAt' => $startedAt,
            'span' => $span,
            'points' => [[0.0, 0], ...$points->map(fn (array $point): array => [$point['x'], $point['count']])->all()],
        ];
    }
}
