<?php

namespace App\Filament\Resources\Ballots\Pages;

use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\BallotIntegrity;
use App\Models\Ballot;
use App\Models\Election;
use App\Services\AdminActivityLog;
use App\Services\BallotCsvExport;
use App\Services\BallotIntegrityCheck;
use App\Services\BallotReset;
use App\Services\BallotReviewUndo;
use App\Services\BallotTally;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListBallots extends ListRecords
{
    protected static string $resource = BallotResource::class;

    /**
     * Unix time the page was opened. Ballots sent after it are counted as new until the page is reloaded.
     */
    public int $loadedAt = 0;

    /**
     * Whether the list and its summary refresh on their own. Paused, the page stays still while an admin reads it.
     */
    public bool $liveUpdates = true;

    /**
     * The ballot summary the page last drew. The page only redraws when a check finds a different one.
     */
    public string $fingerprint = '';

    /**
     * Unix time of the last change the page picked up, shown in the live status.
     */
    public int $lastChangedAt = 0;

    /**
     * Tighter rows for admins who scan many ballots at once. Remembered per admin in the session.
     */
    #[Session('admin.ballots.compact')]
    public bool $compactRows = false;

    public function mount(): void
    {
        parent::mount();

        $this->loadedAt = now()->timestamp;
        $this->fingerprint = Ballot::liveFingerprint();
        $this->lastChangedAt = $this->loadedAt;

        $receiptCode = request()->query('kode');

        if (is_string($receiptCode) && filled($receiptCode)) {
            $this->tableSearch = Str::upper(Str::trim($receiptCode));
        }
    }

    /**
     * Runs on a short poll. A check that finds nothing new skips rendering, so an idle page costs one small query
     * instead of redrawing the list, tally, and charts. A change is picked up on the next check.
     */
    public function checkForChanges(): void
    {
        $fingerprint = Ballot::liveFingerprint();

        if (! $this->liveUpdates || $fingerprint === $this->fingerprint) {
            $this->skipRender();

            return;
        }

        $this->fingerprint = $fingerprint;
        $this->lastChangedAt = now()->timestamp;
    }

    public function newBallotsSinceLoad(): int
    {
        return Ballot::query()
            ->where('created_at', '>', Carbon::createFromTimestampUTC($this->loadedAt))
            ->count();
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Ballot::liveCounts();

        return [
            'all' => Tab::make('Semua')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->badge($counts['all']),
            'recent' => Tab::make('Baru')
                ->icon(Heroicon::OutlinedSparkles)
                ->modifyQueryUsing(fn (Builder $query) => $query->recent())
                ->badge($counts['recent']),
            'today' => Tab::make('Hari ini')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->modifyQueryUsing(fn (Builder $query) => $query->sentToday())
                ->badge($counts['today']),
            'problems' => Tab::make('Bermasalah')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->modifyQueryUsing(fn (Builder $query) => $query->withProblems())
                ->badge($counts['problems'])
                ->badgeColor('danger'),
            'review' => Tab::make('Perlu ditinjau')
                ->icon(Heroicon::OutlinedFlag)
                ->modifyQueryUsing(fn (Builder $query) => $query->needsReview())
                ->badge($counts['review'])
                ->badgeColor('warning'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BallotIntegrity::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleLive')
                ->label(fn (): string => $this->liveUpdates ? 'Jeda pembaruan' : 'Lanjutkan pembaruan')
                ->icon(fn (): Heroicon => $this->liveUpdates ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                ->color('gray')
                ->action(function (): void {
                    $this->liveUpdates = ! $this->liveUpdates;
                }),
            Action::make('toggleDensity')
                ->label(fn (): string => $this->compactRows ? 'Tampilan lega' : 'Tampilan ringkas')
                ->icon(fn (): Heroicon => $this->compactRows ? Heroicon::OutlinedArrowsPointingOut : Heroicon::OutlinedArrowsPointingIn)
                ->color('gray')
                ->action(function (): void {
                    $this->compactRows = ! $this->compactRows;
                }),
            Action::make('findReceipt')
                ->label('Cari kode tanda terima')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('gray')
                ->modalHeading('Cari kode tanda terima')
                ->modalDescription('Tempel atau ketik kode dari tanda terima pemilih. Daftar akan disaring ke suara tersebut.')
                ->modalSubmitActionLabel('Cari')
                ->schema([
                    TextInput::make('receiptCode')
                        ->label('Kode tanda terima')
                        ->required()
                        ->autocomplete(false),
                ])
                ->action(function (array $data): void {
                    $code = Str::upper(Str::trim($data['receiptCode']));

                    if (! Ballot::query()->where('receipt_code', $code)->exists()) {
                        Notification::make()->title('Kode tidak ditemukan.')->danger()->send();

                        return;
                    }

                    $this->tableSearch = $code;

                    // The code matched a stored receipt, so it is safe to put in the address bar as a link.
                    $this->js('history.replaceState(null, "", "'.url()->current().'?kode='.$code.'")');

                    Notification::make()->title('Suara ditemukan.')->success()->send();
                }),
            Action::make('export')
                ->label(fn (): string => 'Ekspor CSV ('.$this->getFilteredTableQuery()->count().')')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (BallotCsvExport $exporter, AdminActivityLog $activityLog): StreamedResponse {
                    $ballots = $this->getFilteredTableQuery();

                    if ($this->isSuperAdmin()) {
                        $activityLog->record('Mengekspor CSV dengan identitas pemilih ('.$ballots->count().' suara).');
                    }

                    return $exporter->stream($ballots, $this->isSuperAdmin());
                }),
            ActionGroup::make([
                Action::make('printSummary')
                    ->label('Cetak ringkasan')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->action(function (BallotTally $tally): StreamedResponse {
                        $summary = $tally->forBallots($this->getFilteredTableQuery());

                        $pdf = Pdf::loadView('admin.ringkasan-suara', [
                            'summary' => $summary,
                            'election' => Election::current(),
                            'printedAt' => now()->timezone('Asia/Jakarta'),
                        ])->setPaper('a4')->output();

                        return response()->streamDownload(
                            fn () => print $pdf,
                            'ringkasan-suara-'.now('Asia/Jakarta')->format('Ymd-His').'.pdf',
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('notVoted')
                    ->label('Belum memilih')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->url(fn (): string => UserResource::getUrl('index', ['tableFilters' => ['voted' => ['value' => '0']]])),
                Action::make('verifyIntegrity')
                    ->label('Verifikasi integritas')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->action(function (BallotIntegrityCheck $check, AdminActivityLog $activityLog): void {
                        $report = $check->run();
                        $activityLog->record('Memverifikasi integritas surat suara: '.($report['total'] === 0 ? 'tidak ada masalah.' : $report['total'].' masalah ditemukan.'));

                        $notification = Notification::make()
                            ->title($report['total'] === 0 ? 'Semua surat suara valid.' : $report['total'].' masalah ditemukan.');

                        if ($report['total'] === 0) {
                            $notification->success()->send();

                            return;
                        }

                        $notification->danger()->body(implode(', ', array_filter([
                            $report['withoutPicks'] ? $report['withoutPicks'].' tanpa pilihan' : null,
                            $report['brokenPicks'] ? $report['brokenPicks'].' pilihan tidak valid' : null,
                            $report['orphanedBallots'] ? $report['orphanedBallots'].' tanpa pemilih' : null,
                            $report['duplicateReceipts'] ? $report['duplicateReceipts'].' kode tanda terima ganda' : null,
                        ])))->persistent()->send();
                    }),
                Action::make('resetBallots')
                    ->label('Reset suara')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->outlined()
                    ->visible(fn (): bool => $this->isSuperAdmin())
                    ->modalHeading('Reset semua suara?')
                    ->modalDescription(fn (): string => 'Semua surat suara ('.Ballot::count().') beserta pilihannya akan dihapus permanen dan tidak bisa dikembalikan. Pemilih, bidang, kandidat, dan pengaturan pemilihan tetap ada.')
                    ->modalSubmitActionLabel('Reset semua suara')
                    ->form([
                        TextInput::make('confirmation')
                            ->label('Ketik RESET untuk melanjutkan')
                            ->required()
                            ->in(['RESET'])
                            ->validationMessages(['in' => 'Ketik RESET persis seperti itu.']),
                        TextInput::make('password')
                            ->label('Password Anda')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),
                    ])
                    ->action(function (array $data, BallotReset $ballotReset, AdminActivityLog $activityLog): void {
                        abort_unless($this->isSuperAdmin(), 403);

                        $removed = $ballotReset->reset();
                        $activityLog->record('Mereset '.$removed.' surat suara.', notifyAdmins: true);

                        Log::warning('Seluruh surat suara direset oleh super admin.', [
                            'user_id' => auth()->id(),
                            'ballots_removed' => $removed,
                        ]);

                        Notification::make()
                            ->title($removed.' surat suara direset.')
                            ->body('Pemilih belum memilih lagi dan bisa mengirim suara kembali.')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Lainnya')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Whether a search, filter, or tab narrows the list. An empty list then means no match, not no ballots yet.
     */
    public function hasActiveTableFilters(): bool
    {
        $tabNarrows = ! in_array($this->activeTab, [null, '', 'all'], true);
        // Filament stores an inactive toggle as false, and filled() counts false as filled, so each value is checked on its own.
        $filterApplied = collect($this->tableFilters ?? [])->flatten()->contains(fn (mixed $value): bool => ! in_array($value, [null, false, ''], true));

        return filled($this->tableSearch) || $tabNarrows || $filterApplied;
    }

    /**
     * Clear every search, filter, and tab so the list shows all ballots again.
     */
    public function resetBallotFilters(): void
    {
        $this->resetTableFiltersForm();
        $this->resetTableSearch();
        $this->activeTab = 'all';
    }

    /**
     * Restore the review flags an admin changed a moment ago, from the "Batalkan" button in the notification.
     */
    #[On('ballots-review-undo')]
    public function undoReviewChange(string $token): void
    {
        $restored = app(BallotReviewUndo::class)->restore($token, app(AdminActivityLog::class));

        if ($restored === null) {
            Notification::make()
                ->warning()
                ->title('Tidak bisa dibatalkan.')
                ->body('Perubahan sudah lewat batas waktu, atau dibuat oleh admin lain.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Perubahan dibatalkan.')
            ->body($restored.' surat suara dikembalikan seperti semula.')
            ->send();
    }

    private function isSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }
}
