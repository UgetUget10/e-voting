<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use App\Models\Vote;
use App\Services\BallotService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class CandidateStats extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.candidate-stats';

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $candidateCount = Candidate::count();
        $photoCount = Candidate::query()->whereNotNull('photo_path')->count();
        $atLimitCount = Candidate::query()
            ->whereIn('id', Vote::query()
                ->select('candidate_id')
                ->groupBy('candidate_id')
                ->havingRaw('count(distinct field_id) >= ?', [BallotService::MAX_FIELDS_PER_CANDIDATE]))
            ->count();

        return [
            'cards' => [
                [
                    'label' => 'Jumlah kandidat',
                    'value' => $candidateCount,
                    'hint' => 'Tersedia di semua bidang',
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedUserGroup,
                    'progress' => null,
                ],
                [
                    'label' => 'Belum dipilih',
                    'value' => Candidate::query()->whereDoesntHave('votes')->count(),
                    'hint' => 'Kandidat tanpa pilihan masuk',
                    'tone' => null,
                    'icon' => Heroicon::OutlinedHandRaised,
                    'progress' => null,
                ],
                [
                    'label' => 'Mencapai batas',
                    'value' => $atLimitCount,
                    'hint' => 'Sudah dipilih di '.BallotService::MAX_FIELDS_PER_CANDIDATE.' bidang',
                    'tone' => 'warning',
                    'icon' => Heroicon::OutlinedNoSymbol,
                    'progress' => null,
                ],
                [
                    'label' => 'Foto terpasang',
                    'value' => $photoCount,
                    'hint' => ($candidateCount - $photoCount).' kandidat belum ada foto',
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedCamera,
                    'progress' => $candidateCount > 0 ? (int) round($photoCount / $candidateCount * 100) : 0,
                ],
            ],
        ];
    }
}
