<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActionNeeded;
use App\Filament\Widgets\AdminActivityFeed;
use App\Filament\Widgets\AdminShortcuts;
use App\Filament\Widgets\BallotsByHour;
use App\Filament\Widgets\ClosingChecklist;
use App\Filament\Widgets\ElectionReadiness;
use App\Filament\Widgets\LatestBallots;
use App\Filament\Widgets\NotVotedVoters;
use App\Filament\Widgets\ParticipationByDay;
use App\Filament\Widgets\VotingCountdown;
use App\Filament\Widgets\VotingSpeed;
use App\Filament\Widgets\VotingStats;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Services\AdminActivityLog;
use App\Services\ResultService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ElectionDashboard extends Dashboard
{
    protected static ?string $title = 'Ringkasan Pemilihan';

    protected static ?string $navigationLabel = 'Ringkasan';

    /**
     * The per-resource stat widgets belong on their own list pages, so the dashboard shows only the
     * overview: countdown, what needs attention, turnout, readiness, and recent activity.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            VotingCountdown::class,
            ActionNeeded::class,
            AdminShortcuts::class,
            VotingStats::class,
            ClosingChecklist::class,
            ParticipationByDay::class,
            ElectionReadiness::class,
            VotingSpeed::class,
            LatestBallots::class,
            AdminActivityFeed::class,
        ];
    }

    /**
     * The countdown and what needs attention stay above the tabs, so they are in view from every tab. The rest
     * is split by what an admin is doing: checking the state of things, watching turnout, or reviewing what happened.
     */
    public function getWidgetsContentComponent(): Component
    {
        $grid = fn (array $widgets): Grid => Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($widgets));

        $attention = ActionNeeded::attentionCount();
        $pendingVoters = User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count();

        return Grid::make(1)->schema([
            $grid([VotingCountdown::class]),
            $grid([ActionNeeded::class]),
            View::make('filament.partials.tab-indicator')->columnSpanFull(),
            Tabs::make('dashboardTabs')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs([
                    Tab::make('Ringkasan')
                        ->icon(Heroicon::OutlinedSquares2x2)
                        ->badge($attention > 0 ? $attention : null)
                        ->badgeColor('warning')
                        ->schema([$grid([
                            AdminShortcuts::class,
                            VotingStats::class,
                            ClosingChecklist::class,
                            ElectionReadiness::class,
                        ])]),
                    Tab::make('Partisipasi')
                        ->icon(Heroicon::OutlinedChartBar)
                        ->badge($pendingVoters > 0 ? $pendingVoters : null)
                        ->schema([$grid([
                            NotVotedVoters::class,
                            ParticipationByDay::class,
                            VotingSpeed::class,
                            LatestBallots::class,
                        ])]),
                    Tab::make('Aktivitas')
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->schema([$grid([AdminActivityFeed::class])]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleResults')
                ->label(fn (): string => $this->resultsAreShownEarly() ? 'Sembunyikan hasil' : 'Tampilkan hasil sekarang')
                ->icon(fn (): Heroicon => $this->resultsAreShownEarly() ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                ->color(fn (): string => $this->resultsAreShownEarly() ? 'gray' : 'warning')
                ->visible(fn (): bool => Election::current() !== null)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->resultsAreShownEarly() ? 'Sembunyikan hasil dari pemilih?' : 'Tampilkan hasil sebelum voting ditutup?')
                ->modalDescription(fn (): string => $this->resultsAreShownEarly()
                    ? 'Pemilih tidak lagi melihat hasil sampai voting ditutup.'
                    : 'Pemilih akan langsung melihat perolehan suara sementara, meskipun voting masih berjalan.')
                ->action(function (AdminActivityLog $activityLog): void {
                    $election = Election::current();

                    if ($election === null) {
                        return;
                    }

                    $election->update(['show_results_early' => ! $election->show_results_early]);
                    $activityLog->record($election->show_results_early
                        ? 'Menampilkan hasil sementara kepada pemilih.'
                        : 'Menyembunyikan hasil dari pemilih.', notifyAdmins: true);

                    Notification::make()
                        ->title($election->show_results_early ? 'Hasil sekarang terlihat oleh pemilih.' : 'Hasil disembunyikan dari pemilih.')
                        ->success()
                        ->send();
                }),
            Action::make('dailySummary')
                ->label('Ringkasan harian')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->streamDailySummary()),
        ];
    }

    /**
     * A one-page PDF of today: how many voted, when, and where the results stand. Like the other summaries,
     * it never carries receipt codes or voter names.
     */
    private function streamDailySummary(): StreamedResponse
    {
        $pdf = Pdf::loadView('admin.ringkasan-harian', $this->dailySummaryData())->setPaper('a4')->output();

        return response()->streamDownload(
            fn () => print $pdf,
            'ringkasan-harian-'.now('Asia/Jakarta')->format('Ymd-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dailySummaryData(): array
    {
        $election = Election::current();
        $hourly = BallotsByHour::countsForToday();
        $peakCount = max($hourly);
        $peakHour = $peakCount > 0 ? array_search($peakCount, $hourly, true) : null;
        $voters = User::where('is_admin', false)->count();
        $submitted = Ballot::count();

        return [
            'electionName' => $election?->name ?? 'Belum ada pemilihan',
            'status' => $election?->statusLabel() ?? '—',
            'printedAt' => now('Asia/Jakarta'),
            'ballotsToday' => Ballot::query()->sentToday()->count(),
            'submitted' => $submitted,
            'voters' => $voters,
            'turnout' => $voters > 0 ? (int) round($submitted / $voters * 100) : 0,
            'pending' => max(0, $voters - $submitted),
            'problems' => Ballot::query()->withProblems()->count(),
            'peakHour' => $peakHour === null ? null : sprintf('%02d.00', $peakHour),
            'peakCount' => $peakCount,
            'spikes' => array_map(fn (int $hour): string => sprintf('%02d.00', $hour), BallotsByHour::spikeHours($hourly)),
            'hourly' => collect($hourly)->map(fn (int $total, int $hour): array => ['hour' => sprintf('%02d.00', $hour), 'total' => $total])->filter(fn (array $row): bool => $row['total'] > 0)->values()->all(),
            'fields' => app(ResultService::class)->results()->map(fn (array $result): array => [
                'name' => $result['field']->name,
                'total' => (int) $result['candidates']->sum('votes_count'),
                'candidates' => $result['candidates']->sortByDesc('votes_count')->values()->map(fn ($candidate): array => [
                    'name' => $candidate->name,
                    'votes' => (int) $candidate->votes_count,
                    'advances' => $result['winners']->contains('id', $candidate->id),
                ])->all(),
            ])->values()->all(),
        ];
    }

    private function resultsAreShownEarly(): bool
    {
        return (bool) Election::current()?->show_results_early;
    }
}
