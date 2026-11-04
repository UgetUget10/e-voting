<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use Filament\Widgets\ChartWidget;

class VotingSpeed extends ChartWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected ?string $heading = 'Kecepatan voting';

    protected ?string $description = 'Suara masuk per menit, 15 menit terakhir (WIB).';

    protected ?string $maxHeight = '12rem';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    protected function getData(): array
    {
        $minutes = 15;
        $start = now()->subMinutes($minutes - 1)->startOfMinute();

        $perMinute = Ballot::query()
            ->where('created_at', '>=', $start->copy()->utc())
            ->pluck('created_at')
            ->countBy(fn ($castAt): string => $castAt->copy()->startOfMinute()->timezone('Asia/Jakarta')->format('H:i'));

        $labels = [];
        $counts = [];

        for ($step = 0; $step < $minutes; $step++) {
            $label = $start->copy()->addMinutes($step)->timezone('Asia/Jakarta')->format('H:i');
            $labels[] = $label;
            $counts[] = (int) $perMinute->get($label, 0);
        }

        return [
            'datasets' => [[
                'label' => 'Suara masuk',
                'data' => $counts,
                'borderColor' => '#ea580c',
                'backgroundColor' => 'rgba(234, 88, 12, 0.12)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 2,
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
