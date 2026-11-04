<?php

namespace App\Services;

use App\Models\Field;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;

class BallotSubmissionSummary
{
    /**
     * How many registered voters picked in each field, in voting order, against the number of registered voters.
     *
     * @return list<array{name: string, picks: int, voters: int, percent: float}>
     */
    public function perField(int $voterCount): array
    {
        $picksByField = Vote::query()
            ->selectRaw('field_id, count(*) as total')
            ->groupBy('field_id')
            ->pluck('total', 'field_id');

        return Field::query()
            ->ordered()
            ->get()
            ->map(function (Field $field) use ($picksByField, $voterCount): array {
                $picks = (int) $picksByField->get($field->id, 0);

                return [
                    'name' => $field->name,
                    'picks' => $picks,
                    'voters' => $voterCount,
                    'percent' => $voterCount > 0 ? round($picks / $voterCount * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * How many ballots were sent in each hour of each Jakarta (WIB) day, oldest first.
     *
     * @return list<array{day: string, hour: int, total: int}>
     */
    public function perHour(): array
    {
        // Jakarta (WIB) is a fixed UTC+7 with no daylight saving, so the local time can be shifted in SQL.
        return DB::table('ballots')
            ->selectRaw('DATE(DATE_ADD(created_at, INTERVAL 7 HOUR)) as day, HOUR(DATE_ADD(created_at, INTERVAL 7 HOUR)) as hour, count(*) as total')
            ->groupByRaw('day, hour')
            ->orderByRaw('day, hour')
            ->get()
            ->map(fn (object $row): array => [
                'day' => (string) $row->day,
                'hour' => (int) $row->hour,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
