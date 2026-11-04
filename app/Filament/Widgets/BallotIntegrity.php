<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Ballots\BallotResource;
use App\Models\Ballot;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class BallotIntegrity extends StatsOverviewWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    /**
     * The default poll is every 5 seconds, which re-runs these counts over every ballot far too often.
     */
    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Pemeriksaan integritas';

    protected ?string $description = 'Memastikan setiap surat suara punya pilihan dan pemilih. Pilihan per bidang diperiksa lengkap lewat "Verifikasi integritas".';

    protected function getStats(): array
    {
        ['withoutPicks' => $withoutPicks, 'withoutVoter' => $withoutVoter] = Cache::remember(
            'ballots.integrity-counts.'.Ballot::liveFingerprint(),
            Ballot::KEYED_CACHE_SECONDS,
            fn (): array => [
                'withoutPicks' => Ballot::query()->withoutPicks()->count(),
                'withoutVoter' => Ballot::query()->withoutVoter()->count(),
            ],
        );

        $problems = $withoutPicks + $withoutVoter;

        return [
            Stat::make('Surat suara tanpa pilihan', $withoutPicks)
                ->description($withoutPicks === 0 ? 'Semua surat suara berisi pilihan' : 'Perlu diperiksa · klik untuk melihat')
                ->descriptionIcon($withoutPicks === 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                ->color($withoutPicks === 0 ? 'success' : 'danger')
                ->url(BallotResource::getUrl('index', ['tableFilters' => ['issue' => ['value' => 'without_picks']]])),
            Stat::make('Suara tanpa pemilih', $withoutVoter)
                ->description($withoutVoter === 0 ? 'Setiap suara terhubung ke pemilih' : 'Perlu diperiksa · klik untuk melihat')
                ->descriptionIcon($withoutVoter === 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                ->color($withoutVoter === 0 ? 'success' : 'danger')
                ->url(BallotResource::getUrl('index', ['tableFilters' => ['issue' => ['value' => 'without_voter']]])),
            Stat::make('Status data', $problems === 0 ? 'Bersih' : 'Perlu diperiksa')
                ->description($problems === 0 ? 'Tidak ada masalah ditemukan' : $problems.' masalah ditemukan')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color($problems === 0 ? 'success' : 'warning'),
        ];
    }
}
