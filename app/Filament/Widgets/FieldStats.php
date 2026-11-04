<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Services\FieldStandings;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class FieldStats extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.field-stats';

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $standings = app(FieldStandings::class)->all();
        $fieldCount = $standings->count();
        $emptyCount = $standings->where('status', FieldStandings::EMPTY)->count();
        $tiedCount = $standings->where('status', FieldStandings::TIED)->count();
        $filledCount = $fieldCount - $emptyCount;

        return [
            'cards' => [
                [
                    'label' => 'Jumlah bidang',
                    'value' => $fieldCount,
                    'hint' => Candidate::count().' kandidat tersedia untuk semua bidang',
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedRectangleStack,
                    'progress' => null,
                ],
                [
                    'label' => 'Total suara',
                    'value' => (int) $standings->sum('total'),
                    'hint' => Ballot::count().' surat suara terkirim, termasuk koreksi',
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedClipboardDocumentCheck,
                    'progress' => null,
                ],
                [
                    'label' => 'Bidang seri',
                    'value' => $tiedCount,
                    'hint' => $tiedCount > 0 ? 'Belum ada pemenang tunggal' : 'Tidak ada bidang yang seri',
                    'tone' => $tiedCount > 0 ? 'warning' : null,
                    'icon' => Heroicon::OutlinedArrowsRightLeft,
                    'progress' => null,
                ],
                [
                    'label' => 'Sudah terisi',
                    'value' => $filledCount,
                    'hint' => $emptyCount > 0 ? $emptyCount.' bidang belum ada suara' : 'Semua bidang sudah terisi',
                    'tone' => null,
                    'icon' => Heroicon::OutlinedScale,
                    'progress' => $fieldCount > 0 ? (int) round($filledCount / $fieldCount * 100) : 0,
                ],
            ],
        ];
    }
}
