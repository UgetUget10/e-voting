<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use Filament\Widgets\ChartWidget;

class ParticipationByDay extends ChartWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -10;

    protected ?string $heading = 'Partisipasi 7 hari terakhir';

    protected ?string $description = 'Jumlah suara masuk per hari (WIB).';

    protected ?string $maxHeight = '14rem';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    protected function getData(): array
    {
        $start = now('Asia/Jakarta')->startOfDay()->subDays(6);

        $perDay = Ballot::query()
            ->where('created_at', '>=', $start->copy()->utc())
            ->pluck('created_at')
            ->countBy(fn ($castAt): string => $castAt->timezone('Asia/Jakarta')->toDateString());

        $labels = [];
        $counts = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $start->copy()->addDays($offset);
            $labels[] = $day->locale('id')->translatedFormat('j M');
            $counts[] = (int) $perDay->get($day->toDateString(), 0);
        }

        return [
            'datasets' => [[
                'label' => 'Suara masuk',
                'data' => $counts,
                // Today is the last bar, drawn in the strong accent; earlier days are softer.
                'backgroundColor' => array_map(fn (int $index): string => $index === 6 ? '#c2410c' : '#fdba74', array_keys($counts)),
                'borderRadius' => 6,
            ]],
            'labels' => $labels,
        ];
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
