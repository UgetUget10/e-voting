<?php

namespace App\Filament\Widgets;

use App\Models\Election;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;

class ElectionTimeline extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.election-timeline';

    /**
     * Every election with a dated voting window, placed on one shared time axis. Each bar starts and ends at
     * its share of the axis, so overlapping elections show up as bars that sit on top of each other.
     *
     * @return array{rows: list<array{name: string, status: string, left: float, width: float, opens: string, closes: string}>, axisStart: ?CarbonInterface, axisEnd: ?CarbonInterface, nowLeft: ?float, undated: int}
     */
    protected function getViewData(): array
    {
        $elections = Election::query()->orderBy('opens_at')->get();
        $dated = $elections->filter(fn (Election $election): bool => $election->opens_at !== null && $election->closes_at !== null);
        $undated = $elections->count() - $dated->count();

        if ($dated->isEmpty()) {
            return ['rows' => [], 'axisStart' => null, 'axisEnd' => null, 'nowLeft' => null, 'undated' => $undated];
        }

        $axisStart = $dated->min('opens_at');
        $axisEnd = $dated->max('closes_at');
        $span = max(1, $axisEnd->timestamp - $axisStart->timestamp);

        $rows = $dated->map(fn (Election $election): array => [
            'name' => $election->name,
            'status' => $election->statusLabel(),
            'left' => round(($election->opens_at->timestamp - $axisStart->timestamp) / $span * 100, 2),
            'width' => round(max(0.5, ($election->closes_at->timestamp - $election->opens_at->timestamp) / $span * 100), 2),
            'opens' => $election->opens_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'),
            'closes' => $election->closes_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'),
        ])->values()->all();

        $nowLeft = now()->timestamp < $axisStart->timestamp || now()->timestamp > $axisEnd->timestamp
            ? null
            : round((now()->timestamp - $axisStart->timestamp) / $span * 100, 2);

        return [
            'rows' => $rows,
            'axisStart' => $axisStart,
            'axisEnd' => $axisEnd,
            'nowLeft' => $nowLeft,
            'undated' => $undated,
        ];
    }
}
