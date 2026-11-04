<?php

namespace App\Filament\Widgets;

use App\Models\Election;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class ElectionStats extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.election-stats';

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $activeElection = Election::current();

        return [
            'cards' => [
                [
                    'label' => 'Pemilihan aktif',
                    'value' => $activeElection?->statusLabel() ?? 'Belum ada',
                    'hint' => $activeElection?->name ?? 'Buat pemilihan baru',
                    'text' => true,
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedCalendarDays,
                ],
                [
                    'label' => 'Sedang dibuka',
                    'value' => Election::query()->open()->count(),
                    'hint' => 'Menerima suara sekarang',
                    'text' => false,
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedPlay,
                ],
                [
                    'label' => 'Belum dibuka',
                    'value' => Election::query()->upcoming()->count(),
                    'hint' => 'Menunggu jadwal buka',
                    'text' => false,
                    'tone' => 'warning',
                    'icon' => Heroicon::OutlinedClock,
                ],
                [
                    'label' => 'Ditutup',
                    'value' => Election::query()->closed()->count(),
                    'hint' => 'Sudah melewati jadwal tutup',
                    'text' => false,
                    'tone' => null,
                    'icon' => Heroicon::OutlinedLockClosed,
                ],
            ],
        ];
    }
}
