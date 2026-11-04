<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use Filament\Support\Colors\Color;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class BallotsByHour extends ChartWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    /**
     * Refreshes with the list, so the numbers in the panel keep up while votes are coming in.
     */
    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Suara masuk per jam';

    protected ?string $description = 'Hari ini, per jam (WIB). Batang merah menandai lonjakan yang tidak biasa.';

    protected ?string $maxHeight = '12rem';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    protected function getData(): array
    {
        $counts = self::countsForToday();
        $spikes = self::spikeHours($counts);

        $labels = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
        }

        return [
            'datasets' => [[
                'label' => 'Suara masuk',
                'data' => $counts,
                'backgroundColor' => array_map(fn (int $hour): string => in_array($hour, $spikes, true) ? Color::Red[600] : Color::Orange[600], array_keys($counts)),
                'borderRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * Ballots sent today (Jakarta time) for each hour of the day, index = hour, always 24 entries.
     *
     * @return list<int>
     */
    public static function countsForToday(): array
    {
        return Cache::remember(
            'ballots.hourly.'.now('Asia/Jakarta')->toDateString().'.'.Ballot::liveFingerprint(),
            Ballot::KEYED_CACHE_SECONDS,
            function (): array {
                $perHour = Ballot::query()
                    ->sentToday()
                    // Jakarta (WIB) is a fixed UTC+7 with no daylight saving, so the hour can be shifted in SQL.
                    ->selectRaw('HOUR(DATE_ADD(created_at, INTERVAL 7 HOUR)) as hour, count(*) as total')
                    ->groupByRaw('hour')
                    ->pluck('total', 'hour');

                return collect(range(0, 23))
                    ->map(fn (int $hour): int => (int) $perHour->get($hour, 0))
                    ->all();
            },
        );
    }

    /**
     * Hours whose count is far above the day's norm (mean plus two standard deviations, and at least 5
     * ballots), shown in red so a sudden burst of votes gets a second look.
     *
     * @param  list<int>  $counts  Ballots per hour, index = hour of day.
     * @return list<int>
     */
    public static function spikeHours(array $counts): array
    {
        $mean = array_sum($counts) / max(1, count($counts));
        $variance = array_sum(array_map(fn (int $count): float => ($count - $mean) ** 2, $counts)) / max(1, count($counts));
        $threshold = max(5, $mean + 2 * sqrt($variance));

        return array_keys(array_filter($counts, fn (int $count): bool => $count > $threshold));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
