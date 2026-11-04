<?php

namespace App\Filament\Resources\Ballots\Tables;

use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Widgets\BallotsByHour;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\BallotNote;
use App\Models\Candidate;
use App\Models\Field;
use App\Models\Vote;
use App\Services\AdminActivityLog;
use App\Services\BallotCsvExport;
use App\Services\BallotReviewUndo;
use App\Services\BallotTally;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BallotsTable
{
    /**
     * How often the list refreshes on its own, so new ballots show up while votes are coming in.
     */
    public const POLL_SECONDS = 5;

    public static function configure(Table $table): Table
    {
        return $table
            ->header(fn (ListBallots $livewire): View => view('filament.ballots.header', [
                'tally' => self::cachedTally($livewire),
                'cards' => self::overviewCards(),
                'newBallots' => $livewire->newBallotsSinceLoad(),
                'liveUpdates' => $livewire->liveUpdates,
                'pollSeconds' => self::POLL_SECONDS,
                'updatedAt' => Carbon::createFromTimestamp($livewire->lastChangedAt, 'Asia/Jakarta'),
                'spikes' => self::todaysSpikes(),
                'total' => Ballot::liveCounts()['all'],
            ]))
            ->recordClasses(fn (Ballot $record, ListBallots $livewire): array => array_filter([
                $record->created_at->gt(Ballot::recentSince()) ? 'admin-ballot-row-new' : null,
                $record->needs_review ? 'admin-ballot-flagged' : null,
                $livewire->compactRows ? 'admin-ballot-compact' : null,
            ]))
            ->persistColumnsInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->columns([
                ...self::voterColumns(),
                TextColumn::make('receipt_code')
                    ->label('Kode tanda terima')
                    ->weight('semibold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Kode disalin.')
                    ->searchable(),
                TextColumn::make('votes')
                    ->label('Pilihan')
                    ->getStateUsing(fn (Ballot $record): array => self::picksFor($record))
                    ->badge()
                    ->color('gray')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('Tanpa pilihan')
                    ->toggleable(),
                TextColumn::make('notes_count')
                    ->label('Catatan')
                    ->badge()
                    ->color(fn (?int $state): string => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn (?int $state): ?string => $state ? (string) $state : null)
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('needs_review')
                    ->label('Tinjauan')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'Perlu ditinjau' : null)
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'))
                    ->description(fn (Ballot $record): string => ($record->created_at->gt(Ballot::recentSince()) ? 'Baru · ' : '').$record->created_at->locale('id')->diffForHumans())
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('needs_review')
                    ->label('Perlu ditinjau')
                    ->toggle()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['isActive'] ?? false, fn (Builder $query) => $query->needsReview())),
                Filter::make('recent')
                    ->label('Baru dikirim ('.Ballot::RECENT_MINUTES.' menit terakhir)')
                    ->toggle()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['isActive'] ?? false, fn (Builder $query) => $query->recent())),
                SelectFilter::make('issue')
                    ->label('Masalah data')
                    ->options([
                        'without_picks' => 'Tanpa pilihan',
                        'without_voter' => 'Tanpa pemilih',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'without_picks' => $query->withoutPicks(),
                        'without_voter' => $query->withoutVoter(),
                        default => $query,
                    }),
                SelectFilter::make('hour')
                    ->label('Jam kirim hari ini (WIB)')
                    ->options(collect(range(0, 23))->mapWithKeys(fn (int $hour): array => [$hour => sprintf('%02d.00 - %02d.59', $hour, $hour)])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($data['value'] ?? null) !== null && $data['value'] !== '',
                        function (Builder $query) use ($data): Builder {
                            $start = now('Asia/Jakarta')->startOfDay()->addHours((int) $data['value']);

                            return $query->where('created_at', '>=', $start->copy()->utc())
                                ->where('created_at', '<', $start->copy()->addHour()->utc());
                        },
                    )),
                Filter::make('sent_between')
                    ->label('Tanggal kirim')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari tanggal')
                            ->native(false)
                            ->displayFormat('j M Y'),
                        DatePicker::make('until')
                            ->label('Sampai tanggal')
                            ->native(false)
                            ->displayFormat('j M Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $query) => $query
                            ->where('created_at', '>=', Carbon::parse($data['from'], 'Asia/Jakarta')->startOfDay()->utc()))
                        ->when(filled($data['until'] ?? null), fn (Builder $query) => $query
                            ->where('created_at', '<=', Carbon::parse($data['until'], 'Asia/Jakarta')->endOfDay()->utc())))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['from'] ?? null) ? 'Dari '.Carbon::parse($data['from'])->locale('id')->translatedFormat('j M Y') : null,
                        filled($data['until'] ?? null) ? 'Sampai '.Carbon::parse($data['until'])->locale('id')->translatedFormat('j M Y') : null,
                    ]))),
                SelectFilter::make('field')
                    ->label('Bidang')
                    ->options(fn (): array => Field::query()->ordered()->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('votes', fn (Builder $query) => $query->where('field_id', $data['value']))
                        : $query),
                SelectFilter::make('candidate')
                    ->label('Kandidat')
                    ->options(fn (): array => Candidate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('votes', fn (Builder $query) => $query->where('candidate_id', $data['value']))
                        : $query),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('notes')
                ->with(['votes.field', 'votes.candidate'])
                ->when(self::canSeeVoters(), fn (Builder $query) => $query->with('user')))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordActions([
                ViewAction::make()
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modal()
                    ->slideOver()
                    ->modalHeading('Detail suara')
                    ->modalDescription(fn (Ballot $record, ListBallots $livewire): ?string => self::detailPositionLabel($record, $livewire))
                    ->extraModalFooterActions(fn (Ballot $record, ListBallots $livewire): array => self::detailNavigationActions($record, $livewire))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->mountUsing(function (Ballot $record, AdminActivityLog $activityLog): void {
                        if (self::canSeeVoters()) {
                            $activityLog->record('Membuka identitas pemilih pada surat suara '.$record->receipt_code.'.', subject: $record);
                        }
                    })
                    ->schema([
                        Section::make('Tanda terima')
                            ->schema([
                                TextEntry::make('receipt_code')
                                    ->label('Kode')
                                    ->fontFamily('mono')
                                    ->size('lg')
                                    ->weight('semibold')
                                    ->copyable()
                                    ->copyMessage('Kode disalin.'),
                                TextEntry::make('created_at')
                                    ->label('Dikirim')
                                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i').' WIB'),
                                ViewEntry::make('picks')
                                    ->label('Pilihan per bidang')
                                    ->getStateUsing(fn (Ballot $record): array => self::picksAsPairs($record))
                                    ->view('filament.ballots.picks-chips'),
                            ]),
                        Section::make('Pemilih')
                            ->description('Hanya super admin. Melihat identitas ini tercatat di log aktivitas.')
                            ->visible(self::canSeeVoters())
                            ->schema(self::voterEntries()),
                        Section::make('Catatan internal')
                            ->description('Hanya untuk admin. Catatan tidak mengubah surat suara.')
                            ->schema([
                                ViewEntry::make('noteList')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn (Ballot $record) => $record->notes)
                                    ->view('filament.ballots.notes-list'),
                            ]),
                        Section::make('Riwayat')
                            ->description('Tindakan admin pada surat suara ini, termasuk penandaan dan catatan.')
                            ->schema([
                                ViewEntry::make('history')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn (Ballot $record): array => self::historyFor($record))
                                    ->view('filament.ballots.history'),
                            ]),
                    ])
                    ->extraModalWindowAttributes(['class' => 'admin-ballot-detail']),
                Action::make('toggleReview')
                    ->label(fn (Ballot $record): string => $record->needs_review ? 'Hapus tanda' : 'Tandai')
                    ->icon(Heroicon::OutlinedFlag)
                    ->color(fn (Ballot $record): string => $record->needs_review ? 'warning' : 'gray')
                    ->action(function (Ballot $record, AdminActivityLog $activityLog): void {
                        $previous = $record->needs_review;

                        $record->update(['needs_review' => ! $previous]);

                        $activityLog->record($record->needs_review
                            ? 'Menandai surat suara '.$record->receipt_code.' untuk ditinjau.'
                            : 'Menghapus tanda tinjauan pada surat suara '.$record->receipt_code.'.', subject: $record);

                        Notification::make()
                            ->title($record->needs_review ? 'Ditandai untuk ditinjau.' : 'Tanda tinjauan dihapus.')
                            ->success()
                            ->actions([self::undoReviewAction(app(BallotReviewUndo::class)->remember([$record->getKey() => (bool) $previous]))])
                            ->send();
                    }),
                Action::make('note')
                    ->label('Catatan')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->modal()
                    ->modalHeading('Tambah catatan internal')
                    ->modalDescription('Catatan hanya terlihat oleh admin dan tidak mengubah surat suara.')
                    ->modalContent(fn (Ballot $record): View => view('filament.ballots.notes-list', ['getState' => fn () => $record->notes]))
                    ->modalSubmitActionLabel('Simpan catatan')
                    ->schema([
                        Textarea::make('body')
                            ->label('Catatan baru')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (array $data, Ballot $record, AdminActivityLog $activityLog): void {
                        $record->notes()->create(['user_id' => auth()->id(), 'body' => $data['body']]);

                        $activityLog->record('Menambahkan catatan pada surat suara '.$record->receipt_code.'.', subject: $record);

                        Notification::make()->title('Catatan disimpan.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportSelected')
                        ->label('Ekspor yang dipilih')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, BallotCsvExport $exporter, AdminActivityLog $activityLog): StreamedResponse {
                            if (self::canSeeVoters()) {
                                $activityLog->record('Mengekspor CSV dengan identitas pemilih ('.$records->count().' suara dipilih).');
                            }

                            return $exporter->stream(Ballot::query()->whereKey($records->modelKeys()), self::canSeeVoters());
                        }),
                    BulkAction::make('noteSelected')
                        ->label('Tambah catatan')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->modalHeading('Tambah catatan ke suara terpilih')
                        ->modalDescription('Catatan yang sama ditambahkan ke setiap suara yang dipilih. Catatan hanya terlihat oleh admin.')
                        ->modalSubmitActionLabel('Simpan catatan')
                        ->schema([
                            Textarea::make('body')
                                ->label('Catatan baru')
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data, AdminActivityLog $activityLog): void {
                            $records->each(fn (Ballot $ballot): BallotNote => $ballot->notes()->create([
                                'user_id' => auth()->id(),
                                'body' => $data['body'],
                            ]));

                            $activityLog->record('Menambahkan catatan pada '.$records->count().' surat suara dipilih.');

                            Notification::make()->title('Catatan disimpan ke '.$records->count().' suara.')->success()->send();
                        }),
                    BulkAction::make('flagSelected')
                        ->label('Tandai perlu ditinjau')
                        ->icon(Heroicon::OutlinedFlag)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, AdminActivityLog $activityLog) => self::setReviewFlag($records, true, $activityLog)),
                    BulkAction::make('unflagSelected')
                        ->label('Hapus tanda tinjauan')
                        ->icon(Heroicon::OutlinedXMark)
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, AdminActivityLog $activityLog) => self::setReviewFlag($records, false, $activityLog)),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading(fn (ListBallots $livewire): string => $livewire->hasActiveTableFilters() ? 'Tidak ada suara yang cocok' : 'Belum ada suara masuk')
            ->emptyStateDescription(fn (ListBallots $livewire): string => $livewire->hasActiveTableFilters()
                ? 'Coba ubah atau hapus filter dan pencarian.'
                : 'Surat suara yang dikirim pemilih akan muncul di sini dalam beberapa detik. Daftar ini diperbarui otomatis.')
            ->emptyStateActions([self::resetFiltersAction()]);
    }

    /**
     * The overview cards above the tally, counted over every ballot rather than the filtered list. Clicking a
     * card opens the tab with the same name.
     *
     * @return list<array{tab: string, label: string, value: float|int, decimals: int, hint: string, icon: Heroicon, tone: string}>
     */
    public static function overviewCards(): array
    {
        $counts = Ballot::liveCounts();

        return [
            [
                'tab' => 'all',
                'label' => 'Suara masuk',
                'value' => $counts['all'],
                'decimals' => 0,
                'hint' => 'Semua surat suara',
                'icon' => Heroicon::OutlinedClipboardDocumentCheck,
                'tone' => 'primary',
            ],
            [
                'tab' => 'today',
                'label' => 'Hari ini',
                'value' => $counts['today'],
                'decimals' => 0,
                'hint' => 'Sejak pukul 00.00 WIB',
                'icon' => Heroicon::OutlinedCalendarDays,
                'tone' => 'success',
            ],
            [
                'tab' => 'recent',
                'label' => 'Kecepatan',
                'value' => round($counts['recent'] / Ballot::RECENT_MINUTES, 1),
                'decimals' => 1,
                'hint' => 'Suara per menit, '.Ballot::RECENT_MINUTES.' menit terakhir',
                'icon' => Heroicon::OutlinedBolt,
                'tone' => 'accent',
            ],
            [
                'tab' => 'problems',
                'label' => 'Bermasalah',
                'value' => $counts['problems'],
                'decimals' => 0,
                'hint' => 'Tanpa pilihan atau pemilih',
                'icon' => Heroicon::OutlinedExclamationTriangle,
                'tone' => 'danger',
            ],
            [
                'tab' => 'review',
                'label' => 'Perlu ditinjau',
                'value' => $counts['review'],
                'decimals' => 0,
                'hint' => 'Ditandai oleh admin',
                'icon' => Heroicon::OutlinedFlag,
                'tone' => 'warning',
            ],
        ];
    }

    /**
     * @param  Collection<int, Ballot>  $records
     */
    private static function setReviewFlag(Collection $records, bool $needsReview, AdminActivityLog $activityLog): void
    {
        $previous = Ballot::query()->whereKey($records->modelKeys())->pluck('needs_review', 'id')->map(fn (mixed $flag): bool => (bool) $flag)->all();
        $updated = Ballot::query()->whereKey($records->modelKeys())->update(['needs_review' => $needsReview]);

        $activityLog->record($needsReview
            ? 'Menandai '.$updated.' surat suara untuk ditinjau.'
            : 'Menghapus tanda tinjauan dari '.$updated.' surat suara.');

        Notification::make()
            ->title($needsReview ? $updated.' suara ditandai untuk ditinjau.' : 'Tanda tinjauan dihapus dari '.$updated.' suara.')
            ->success()
            ->actions([self::undoReviewAction(app(BallotReviewUndo::class)->remember($previous))])
            ->send();
    }

    private static function resetFiltersAction(): Action
    {
        return Action::make('resetFilters')
            ->label('Hapus filter')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->button()
            ->visible(fn (ListBallots $livewire): bool => $livewire->hasActiveTableFilters())
            ->action(fn (ListBallots $livewire) => $livewire->resetBallotFilters());
    }

    private static function undoReviewAction(string $token): Action
    {
        return Action::make('undoReview')
            ->label('Batalkan')
            ->color('gray')
            ->dispatch('ballots-review-undo', ['token' => $token]);
    }

    /**
     * Where the ballot sits in the rows on screen, and the neighbours the detail modal can step to.
     *
     * @return array{position: int, total: int, previous: ?Ballot, next: ?Ballot}|null
     */
    private static function detailPosition(Ballot $record, ListBallots $livewire): ?array
    {
        $rows = $livewire->getTableRecords();
        $rows = $rows instanceof AbstractPaginator ? $rows->getCollection() : collect($rows);
        $index = $rows->search(fn (Ballot $candidate): bool => $candidate->is($record));

        if ($index === false) {
            return null;
        }

        return [
            'position' => $index + 1,
            'total' => $rows->count(),
            'previous' => $rows->get($index - 1),
            'next' => $rows->get($index + 1),
        ];
    }

    private static function detailPositionLabel(Ballot $record, ListBallots $livewire): ?string
    {
        $position = self::detailPosition($record, $livewire);

        if ($position === null) {
            return null;
        }

        return 'Suara ke '.$position['position'].' dari '.$position['total'].' di halaman ini. Pakai tombol panah kiri dan kanan untuk pindah.';
    }

    /**
     * @return list<Action>
     */
    private static function detailNavigationActions(Ballot $record, ListBallots $livewire): array
    {
        $position = self::detailPosition($record, $livewire);

        if ($position === null) {
            return [];
        }

        return [
            Action::make('previousBallot')
                ->label('Sebelumnya')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->visible($position['previous'] !== null)
                ->extraAttributes(['data-ballot-nav' => 'previous'])
                ->action(fn (ListBallots $livewire) => $livewire->replaceMountedAction('view', [], ['table' => true, 'recordKey' => $position['previous']->getKey()])),
            Action::make('nextBallot')
                ->label('Berikutnya')
                ->icon(Heroicon::OutlinedChevronRight)
                ->color('gray')
                ->visible($position['next'] !== null)
                ->extraAttributes(['data-ballot-nav' => 'next'])
                ->action(fn (ListBallots $livewire) => $livewire->replaceMountedAction('view', [], ['table' => true, 'recordKey' => $position['next']->getKey()])),
        ];
    }

    /**
     * The ballot's own history, oldest first: its submission, then each admin action recorded against it.
     * Admin names are shown only to super admins, the same as the rest of the voter identity.
     *
     * @return list<array{at: CarbonInterface, text: string, by: ?string}>
     */
    private static function historyFor(Ballot $record): array
    {
        $activities = $record->adminActivities()->with('user')->oldest('created_at')->oldest('id')->get();

        return [
            ['at' => $record->created_at, 'text' => 'Surat suara dikirim.', 'by' => null],
            ...$activities->map(fn (AdminActivity $activity): array => [
                'at' => $activity->created_at,
                'text' => $activity->description,
                'by' => self::canSeeVoters() ? ($activity->user?->name ?? 'Sistem') : null,
            ])->all(),
        ];
    }

    /**
     * Only super admins see who cast a ballot. Everyone else identifies ballots by receipt code alone.
     */
    public static function canSeeVoters(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * The tally for the filters on screen, reused for a short while per filter state so polling does not
     * re-aggregate every vote on each refresh.
     *
     * @return array{ballots: int, fields: list<array{name: string, picks: int, candidates: list<array{name: string, votes: int, share: float}>}>}
     */
    public static function cachedTally(ListBallots $livewire): array
    {
        $key = 'ballots.tally.'.md5(json_encode([$livewire->tableFilters, $livewire->tableSearch, $livewire->activeTab])).'.'.Ballot::liveFingerprint().'.'.now()->format('YmdHi');

        return Cache::remember($key, Ballot::KEYED_CACHE_SECONDS, fn (): array => app(BallotTally::class)
            ->forBallots($livewire->getFilteredTableQuery()));
    }

    /**
     * Hours of today whose volume is far above the norm, with their counts, for the spike banner.
     *
     * @return list<array{hour: int, total: int}>
     */
    public static function todaysSpikes(): array
    {
        $counts = BallotsByHour::countsForToday();

        return collect(BallotsByHour::spikeHours($counts))
            ->map(fn (int $hour): array => ['hour' => $hour, 'total' => $counts[$hour]])
            ->values()
            ->all();
    }

    /**
     * @return list<TextColumn>
     */
    private static function voterColumns(): array
    {
        if (! self::canSeeVoters()) {
            return [];
        }

        return [
            TextColumn::make('user.name')
                ->label('Pemilih')
                ->weight('semibold')
                ->searchable(),
            TextColumn::make('user.email')
                ->label('Email')
                ->color('gray')
                ->searchable(),
        ];
    }

    /**
     * @return list<TextEntry>
     */
    private static function voterEntries(): array
    {
        if (! self::canSeeVoters()) {
            return [];
        }

        return [
            TextEntry::make('user.name')
                ->label('Pemilih')
                ->weight('semibold'),
            TextEntry::make('user.email')
                ->label('Email')
                ->copyable(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function picksFor(Ballot $ballot): array
    {
        return array_map(
            fn (array $pick): string => $pick['field'].': '.$pick['candidate'],
            self::picksAsPairs($ballot),
        );
    }

    /**
     * @return list<array{field: string, candidate: string}>
     */
    public static function picksAsPairs(Ballot $ballot): array
    {
        return $ballot->votes
            ->sortBy(fn (Vote $vote): array => [$vote->field->position, $vote->field->id])
            ->map(fn (Vote $vote): array => ['field' => $vote->field->name, 'candidate' => $vote->candidate->name])
            ->values()
            ->all();
    }
}
