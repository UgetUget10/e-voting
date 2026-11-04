<?php

namespace App\Filament\Voter\Pages;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Services\ParticipationTrend;
use App\Services\ResultService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Results extends Page
{
    protected static ?string $slug = 'hasil';

    protected static ?string $title = 'Hasil Voting';

    protected static ?string $navigationLabel = 'Hasil Voting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /**
     * Kept out of the navigation. Voters reach their status from the ballot and the status page itself.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.voter.pages.results';

    public const NOT_VOTED_SESSION_KEY = 'results-not-voted';

    /**
     * Results open for admins and for voters who have voted. A voter who has not voted is sent to the ballot
     * with an alert.
     */
    public function mount(): void
    {
        if (auth()->user()?->is_admin || Vote::hasVoted()) {
            return;
        }

        session()->flash(
            self::NOT_VOTED_SESSION_KEY,
            Election::current()?->votingStatus() === 'upcoming'
                ? 'Voting belum dibuka. Hasil voting bisa dilihat setelah Anda memberikan suara saat voting dibuka.'
                : 'Hasil voting hanya bisa dilihat setelah Anda memberikan suara. Silakan pilih kandidat terlebih dahulu.',
        );

        $this->redirect(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $election = Election::current();
        $resultsVisible = $election?->resultsVisible() ?? false;
        $voterCount = User::where('is_admin', false)->count();
        $ballotCount = Ballot::count();

        return [
            'election' => $election,
            'resultsVisible' => $resultsVisible,
            'results' => $resultsVisible ? app(ResultService::class)->results() : collect(),
            'hasVoted' => (bool) auth()->user()?->ballot()->exists(),
            'isAdmin' => (bool) auth()->user()?->is_admin,
            'voterCount' => $voterCount,
            'ballotCount' => $ballotCount,
            'notVotedCount' => max(0, $voterCount - $ballotCount),
            'updatedAt' => now(),
            'isLive' => ! $election?->hasClosed(),
            'trend' => app(ParticipationTrend::class)->build($voterCount),
        ];
    }
}
