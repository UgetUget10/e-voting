<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Services\CandidateTimeline;
use App\Services\ParticipationTrend;
use App\Services\ResultService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Livewire\Attributes\Url;
use UnitEnum;

class Results extends Page
{
    protected static ?string $title = 'Hasil Voting';

    protected static ?string $navigationLabel = 'Hasil Voting';

    protected static string|UnitEnum|null $navigationGroup = 'Pemilihan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.results';

    public bool $liveUpdates = true;

    public bool $projector = false;

    /**
     * Kept in the address bar (?bidang=ID), so one field can be shared as a link.
     */
    #[Url(as: 'bidang')]
    public ?string $fieldId = null;

    public function toggleProjector(): void
    {
        $this->projector = ! $this->projector;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleLive')
                ->label(fn (): string => $this->liveUpdates ? 'Jeda live' : 'Lanjutkan live')
                ->icon(fn (): Heroicon => $this->liveUpdates ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                ->color('gray')
                ->visible(fn (): bool => ! Election::current()?->hasClosed())
                ->action(function (): void {
                    $this->liveUpdates = ! $this->liveUpdates;
                }),
            Action::make('toggleProjector')
                ->label(fn (): string => $this->projector ? 'Keluar mode proyektor' : 'Mode proyektor')
                ->icon(fn (): Heroicon => $this->projector ? Heroicon::OutlinedArrowsPointingIn : Heroicon::OutlinedPresentationChartBar)
                ->color('gray')
                ->action(fn () => $this->toggleProjector()),
            ActionGroup::make([
                Action::make('copySummary')
                    ->label('Salin ringkasan')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->alpineClickHandler(fn (): string => 'window.navigator.clipboard.writeText('.Js::from($this->summaryText())->toHtml().'); new FilamentNotification().title(\'Ringkasan hasil disalin.\').success().send()'),
                Action::make('downloadCard')
                    ->label('Unduh gambar (PNG)')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->alpineClickHandler('window.adminResultsCard?.download()'),
                Action::make('print')
                    ->label('Cetak')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->alpineClickHandler('window.print()'),
                Action::make('voterPreview')
                    ->label('Lihat sebagai pemilih')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(route('filament.pemilih.pages.hasil'))
                    ->openUrlInNewTab(),
                Action::make('beritaAcara')
                    ->label('Berita acara')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(route('results.berita-acara'))
                    ->openUrlInNewTab(),
            ])
                ->label('Lainnya')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->color('gray')
                ->button(),
            ActionGroup::make([
                Action::make('exportCsv')
                    ->label('CSV')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->url(route('results.export')),
                Action::make('exportXlsx')
                    ->label('Excel (.xlsx)')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->url(route('results.export.xlsx')),
                Action::make('exportPdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(route('results.export.pdf')),
            ])
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->button(),
        ];
    }

    /**
     * Plain-text snapshot of the standings, ready to paste into a chat or a report.
     */
    private function summaryText(): string
    {
        $election = Election::current();
        $lines = [$election ? 'Hasil '.$election->name.' ('.$election->statusLabel().')' : 'Hasil voting'];

        foreach (app(ResultService::class)->results() as $result) {
            $lines[] = '';
            $lines[] = $result['field']->name;

            foreach ($result['candidates']->sortByDesc('votes_count')->values() as $index => $candidate) {
                $advances = $result['winners']->contains('id', $candidate->id) ? ' (lolos)' : '';
                $lines[] = ($index + 1).'. '.$candidate->name.' - '.$candidate->votes_count.' suara'.$advances;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The figures for the downloadable card: every field shown on the page, with each candidate's votes and
     * whether they advance. Only totals are included, never who cast a ballot.
     *
     * @param  Collection<int, array{field: Field, candidates: Collection, winners: Collection}>  $results
     * @return array{title: string, status: string, generatedAt: string, fields: list<array{name: string, candidates: list<array{name: string, votes: int, advances: bool}>}>}
     */
    private function cardData(Collection $results, ?Election $election): array
    {
        return [
            'title' => $election?->name ?? 'Hasil voting',
            'status' => $election?->statusLabel() ?? '',
            'generatedAt' => now('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i').' WIB',
            'fields' => $results->map(fn (array $result): array => [
                'name' => $result['field']->name,
                'candidates' => $result['candidates']
                    ->sortByDesc('votes_count')
                    ->values()
                    ->map(fn (Candidate $candidate): array => [
                        'name' => $candidate->name,
                        'votes' => (int) $candidate->votes_count,
                        'advances' => $result['winners']->contains('id', $candidate->id),
                    ])
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $voterCount = User::where('is_admin', false)->count();
        $ballotCount = Ballot::count();
        $election = Election::current();

        $allResults = app(ResultService::class)->results();
        $results = $this->fieldId === null || $this->fieldId === ''
            ? $allResults
            : $allResults->filter(fn (array $result): bool => (string) $result['field']->getKey() === $this->fieldId)->values();
        $timeline = app(CandidateTimeline::class);

        return [
            'results' => $results,
            'timelines' => $results->mapWithKeys(fn (array $result): array => [
                $result['field']->getKey() => $timeline->build($result['field'], $result['candidates']),
            ])->all(),
            'fieldOptions' => $allResults->mapWithKeys(fn (array $result): array => [$result['field']->getKey() => $result['field']->name])->all(),
            'voterCount' => $voterCount,
            'ballotCount' => $ballotCount,
            'notVotedCount' => User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count(),
            'notVotedUrl' => UserResource::getUrl('index', ['tableFilters' => ['voted' => ['value' => '0']]]),
            'turnout' => $voterCount > 0 ? (int) round($ballotCount / $voterCount * 100) : 0,
            'updatedAt' => now(),
            'pollActive' => $this->liveUpdates && ! ($election?->hasClosed() ?? false),
            'trend' => app(ParticipationTrend::class)->build($voterCount),
            'election' => $election,
            'previousTurnout' => Election::query()->whereNotNull('archived_at')->latest('archived_at')->first()?->results_snapshot['turnout'] ?? null,
            'resultsVisibleToVoters' => $election?->resultsVisible() ?? false,
            'fieldsWithoutVotes' => $allResults->filter(fn (array $result): bool => $result['candidates']->sum('votes_count') === 0)->count(),
            'fieldsWithVotes' => $allResults->filter(fn (array $result): bool => $result['candidates']->sum('votes_count') > 0)->count(),
            'tiedFields' => $allResults->filter(fn (array $result): bool => app(ResultService::class)->isTied($result['candidates']))->count(),
            'cardData' => $this->cardData($results, $election),
        ];
    }
}
