<?php

namespace App\Filament\Resources\Elections\Tables;

use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Resources\Elections\Schemas\ElectionForm;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Services\AdminActivityLog;
use App\Services\ElectionScheduleWarning;
use App\Services\ElectionSnapshot;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ElectionsTable
{
    /**
     * An election that closes within this many minutes is flagged as closing soon.
     */
    private const CLOSING_SOON_MINUTES = 60;

    public static function configure(Table $table): Table
    {
        $activeElectionId = Election::current()?->id;

        return $table
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('archived')
                    ->label('Arsip')
                    ->placeholder('Semua pemilihan')
                    ->trueLabel('Hanya arsip')
                    ->falseLabel('Tanpa arsip')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('archived_at'),
                        false: fn (Builder $query) => $query->whereNull('archived_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->options([
                        'open' => 'Dibuka',
                        'upcoming' => 'Belum dibuka',
                        'closed' => 'Ditutup',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'open' => $query->open(),
                        'upcoming' => $query->upcoming(),
                        'closed' => $query->closed(),
                        default => $query,
                    }),
                SelectFilter::make('schedule')
                    ->label('Jadwal')
                    ->placeholder('Semua jadwal')
                    ->options([
                        'this_week' => 'Dibuka minggu ini',
                        'incomplete' => 'Jadwal belum lengkap',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'this_week' => $query->opensThisWeek(),
                        'incomplete' => $query->scheduleIncomplete(),
                        default => $query,
                    }),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nama pemilihan')
                    ->weight('semibold')
                    ->description(fn (Election $record): ?string => $record->id === $activeElectionId ? 'Pemilihan aktif' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Election $record): string => $record->statusLabel())
                    ->description(fn (Election $record): ?string => self::timeHint($record))
                    ->icon(fn (string $state, Election $record): Heroicon => match (true) {
                        $record->closesWithin(self::CLOSING_SOON_MINUTES) => Heroicon::OutlinedExclamationTriangle,
                        $state === 'Dibuka' => Heroicon::OutlinedCheckCircle,
                        $state === 'Ditutup' => Heroicon::OutlinedLockClosed,
                        default => Heroicon::OutlinedClock,
                    })
                    ->color(fn (string $state, Election $record): string => match (true) {
                        $record->closesWithin(self::CLOSING_SOON_MINUTES) => 'warning',
                        $state === 'Dibuka' => 'success',
                        $state === 'Ditutup' => 'gray',
                        default => 'info',
                    }),
                ViewColumn::make('voting_window')
                    ->label('Jendela voting')
                    ->view('filament.elections.window-progress'),
                TextColumn::make('opens_at')
                    ->label('Jadwal')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatSchedule($state))
                    ->description(fn (Election $record): ?string => $record->closes_at === null
                        ? null
                        : 'sampai '.self::formatSchedule($record->closes_at))
                    ->placeholder('Tidak diatur'),
                TextColumn::make('settings')
                    ->label('Pengaturan')
                    ->state(fn (Election $record): ?array => array_values(array_filter([
                        $record->isLocked() ? 'Terkunci' : null,
                        $record->show_results_early ? 'Hasil dibuka dini' : null,
                    ])) ?: null)
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
            ])
            ->recordClasses(fn (Election $record): string => collect([
                'admin-election-row',
                $record->id === $activeElectionId ? 'admin-election-active' : null,
                $record->updated_at->gt(now()->subSeconds(45)) ? 'admin-row-enter' : null,
            ])->filter()->implode(' '))
            ->recordUrl(fn (Election $record): string => ElectionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('openNow')
                    ->label('Buka sekarang')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->button()
                    ->size(Size::Small)
                    ->authorize('update')
                    ->visible(fn (Election $record): bool => ! $record->isOpen() && ! $record->hasClosed())
                    ->requiresConfirmation()
                    ->modalHeading('Buka voting sekarang?')
                    ->modalDescription(fn (): string => (Field::count() === 0 || Candidate::count() === 0
                        ? 'Peringatan: belum ada '.(Field::count() === 0 ? 'bidang' : 'kandidat').', jadi pemilih belum bisa memilih apa pun. '
                        : '').'Pemilih bisa langsung mengirim suara. Jadwal buka diubah menjadi saat ini.')
                    ->action(function (Election $record, AdminActivityLog $activityLog): void {
                        $record->update(['opens_at' => now()]);
                        $activityLog->record('Membuka voting "'.$record->name.'" sekarang.', notifyAdmins: true);

                        Notification::make()->title('Voting dibuka.')->success()->send();
                    }),
                Action::make('closeNow')
                    ->label('Tutup sekarang')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->button()
                    ->size(Size::Small)
                    ->authorize('update')
                    ->visible(fn (Election $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalHeading('Tutup voting sekarang?')
                    ->modalDescription('Pemilih tidak bisa lagi mengirim suara dan hasil langsung terlihat. Jadwal tutup diubah menjadi saat ini.')
                    ->action(function (Election $record, AdminActivityLog $activityLog): void {
                        $record->update(['closes_at' => now()]);
                        $activityLog->record('Menutup voting "'.$record->name.'" sekarang.', notifyAdmins: true);

                        Notification::make()->title('Voting ditutup.')->success()->send();
                    }),
                ActionGroup::make([
                    ActionGroup::make([
                        self::changeScheduleAction(),
                        self::extendAction(30, 'Tambah 30 menit'),
                        self::extendAction(60, 'Tambah 1 jam'),
                        self::extendCustomAction(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('lock')
                            ->label('Kunci data')
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->color('warning')
                            ->authorize('update')
                            ->visible(fn (Election $record): bool => ! $record->isLocked() && ! $record->hasClosed())
                            ->requiresConfirmation()
                            ->modalHeading('Kunci data pemilihan?')
                            ->modalDescription('Kandidat dan bidang tidak bisa ditambah, diubah, atau dihapus sampai kunci dibuka. Gunakan sebelum voting dimulai.')
                            ->action(function (Election $record, AdminActivityLog $activityLog): void {
                                $record->update(['locked_at' => now()]);
                                $activityLog->record('Mengunci data pemilihan "'.$record->name.'".', notifyAdmins: true);

                                Notification::make()->title('Data pemilihan dikunci.')->success()->send();
                            }),
                        Action::make('unlock')
                            ->label('Buka kunci data')
                            ->icon(Heroicon::OutlinedLockOpen)
                            ->color('gray')
                            ->authorize('update')
                            ->visible(fn (Election $record): bool => $record->isLocked())
                            ->requiresConfirmation()
                            ->modalHeading('Buka kunci data pemilihan?')
                            ->action(function (Election $record, AdminActivityLog $activityLog): void {
                                $record->update(['locked_at' => null]);
                                $activityLog->record('Membuka kunci data pemilihan "'.$record->name.'".', notifyAdmins: true);

                                Notification::make()->title('Kunci data dibuka.')->success()->send();
                            }),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('archive')
                            ->label('Arsipkan')
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->color('gray')
                            ->authorize('update')
                            ->visible(fn (Election $record): bool => $record->hasClosed() && ! $record->isArchived())
                            ->requiresConfirmation()
                            ->modalHeading('Arsipkan pemilihan ini?')
                            ->modalDescription('Hasil akhir disimpan sebagai riwayat dan pemilihan ini tidak lagi menjadi pemilihan aktif. Setelah itu reset suara untuk memulai pemilihan baru.')
                            ->action(function (Election $record, AdminActivityLog $activityLog): void {
                                $record->update([
                                    'results_snapshot' => app(ElectionSnapshot::class)->build(),
                                    'archived_at' => now(),
                                ]);
                                $activityLog->record('Mengarsipkan pemilihan "'.$record->name.'".', notifyAdmins: true);

                                Notification::make()->title('Pemilihan diarsipkan.')->success()->send();
                            }),
                        Action::make('history')
                            ->label('Riwayat hasil')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->color('gray')
                            ->visible(fn (Election $record): bool => $record->isArchived() && $record->results_snapshot !== null)
                            ->modalHeading(fn (Election $record): string => 'Hasil '.$record->name)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalContent(fn (Election $record) => view('filament.elections.snapshot', ['record' => $record])),
                        Action::make('restore')
                            ->label('Aktifkan kembali')
                            ->icon(Heroicon::OutlinedArrowUturnLeft)
                            ->color('gray')
                            ->authorize('update')
                            ->visible(fn (Election $record): bool => $record->isArchived())
                            ->requiresConfirmation()
                            ->action(function (Election $record, AdminActivityLog $activityLog): void {
                                $record->update(['archived_at' => null]);
                                $activityLog->record('Mengaktifkan kembali pemilihan "'.$record->name.'".', notifyAdmins: true);

                                Notification::make()->title('Pemilihan diaktifkan kembali.')->success()->send();
                            }),
                        Action::make('exportResults')
                            ->label('Ekspor hasil')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('gray')
                            ->visible(fn (Election $record): bool => $record->hasClosed() && $record->is(Election::current()))
                            ->url(route('results.export')),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('duplicate')
                            ->label('Duplikat')
                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                            ->color('gray')
                            ->visible(fn (): bool => ElectionResource::canCreate())
                            ->requiresConfirmation()
                            ->modalHeading('Duplikat pemilihan ini?')
                            ->modalDescription('Pemilihan baru dibuat dengan jadwal mulai besok dan langsung menjadi pemilihan aktif bagi pemilih. Atur jadwalnya setelah ini.')
                            ->action(function (Election $record, AdminActivityLog $activityLog): void {
                                $duration = $record->opens_at !== null && $record->closes_at !== null
                                    ? $record->opens_at->diffInMinutes($record->closes_at)
                                    : 480;
                                $opensAt = now()->addDay()->startOfHour();

                                $copy = Election::withoutRecordingAdminActivity(fn (): Election => Election::create([
                                    'name' => self::uniqueCopyName($record->name),
                                    'opens_at' => $opensAt,
                                    'closes_at' => $opensAt->copy()->addMinutes((int) $duration),
                                    'show_results_early' => false,
                                ]));

                                $activityLog->record('Menduplikat pemilihan "'.$record->name.'" menjadi "'.$copy->name.'".');

                                Notification::make()->title('Pemilihan diduplikat: '.$copy->name)->success()->send();
                            }),
                    ])->dropdown(false),
                ])
                    ->label('Lainnya')
                    ->icon(Heroicon::OutlinedEllipsisHorizontal)
                    ->button()
                    ->size(Size::Small),
                EditAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('Belum ada pemilihan')
            ->emptyStateDescription('Buat pemilihan pertama untuk mulai menyiapkan kandidat dan jadwal voting.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Buat pemilihan')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(ElectionResource::getUrl('create'))
                    ->button()
                    ->visible(fn (): bool => ElectionResource::canCreate()),
            ]);
    }

    /**
     * Change both the opening and the closing time in one step, without opening the full edit form.
     */
    private static function changeScheduleAction(): Action
    {
        return Action::make('changeSchedule')
            ->label('Ubah jadwal')
            ->icon(Heroicon::OutlinedCalendar)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Election $record): bool => ! $record->isArchived())
            ->modalHeading(fn (Election $record): string => 'Ubah jadwal "'.$record->name.'"')
            ->modalDescription('Semua jadwal memakai waktu WIB. Kosongkan jika tidak ada batas waktu.')
            ->schema(ElectionForm::scheduleFields())
            ->fillForm(fn (Election $record): array => [
                'opens_at' => $record->opens_at,
                'closes_at' => $record->closes_at,
            ])
            ->action(function (Election $record, array $data, AdminActivityLog $activityLog): void {
                $record->update([
                    'opens_at' => $data['opens_at'] ?? null,
                    'closes_at' => $data['closes_at'] ?? null,
                ]);
                $activityLog->record('Mengubah jadwal pemilihan "'.$record->name.'".', notifyAdmins: true);

                Notification::make()->title('Jadwal pemilihan diperbarui.')->success()->send();

                app(ElectionScheduleWarning::class)->notifyIfOverlapping($record);
            });
    }

    private static function extendAction(int $minutes, string $label): Action
    {
        return Action::make('extend'.$minutes)
            ->label($label)
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            ->authorize('update')
            ->visible(fn (Election $record): bool => $record->isOpen() && $record->closes_at !== null)
            ->requiresConfirmation()
            ->modalHeading($label.'?')
            ->modalDescription('Waktu tutup voting diundur '.$minutes.' menit dari jadwal sekarang.')
            ->action(function (Election $record, AdminActivityLog $activityLog) use ($minutes): void {
                $record->update(['closes_at' => $record->closes_at->copy()->addMinutes($minutes)]);
                $activityLog->record('Memperpanjang voting "'.$record->name.'" '.$minutes.' menit.', notifyAdmins: true);

                Notification::make()->title('Voting diperpanjang '.$minutes.' menit.')->success()->send();
            });
    }

    private static function extendCustomAction(): Action
    {
        return Action::make('extendCustom')
            ->label('Tambah waktu…')
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            ->authorize('update')
            ->visible(fn (Election $record): bool => $record->isOpen() && $record->closes_at !== null)
            ->modalHeading('Tambah waktu voting')
            ->modalDescription('Waktu tutup voting diundur sesuai jumlah menit yang diisi.')
            ->schema([
                TextInput::make('minutes')
                    ->label('Tambah berapa menit?')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(1440)
                    ->suffix('menit'),
            ])
            ->action(function (Election $record, array $data, AdminActivityLog $activityLog): void {
                $minutes = (int) $data['minutes'];

                $record->update(['closes_at' => $record->closes_at->copy()->addMinutes($minutes)]);
                $activityLog->record('Memperpanjang voting "'.$record->name.'" '.$minutes.' menit.', notifyAdmins: true);

                Notification::make()->title('Voting diperpanjang '.$minutes.' menit.')->success()->send();
            });
    }

    private static function uniqueCopyName(string $name): string
    {
        $candidate = $name.' (salinan)';
        $suffix = 2;

        while (Election::query()->where('name', $candidate)->exists()) {
            $candidate = $name.' (salinan '.$suffix++.')';
        }

        return $candidate;
    }

    /**
     * How long until the election closes, or until it opens, shown under its status.
     */
    private static function timeHint(Election $election): ?string
    {
        if ($election->hasClosed()) {
            return null;
        }

        if ($election->closesWithin(self::CLOSING_SOON_MINUTES)) {
            return 'Segera ditutup, '.$election->closes_at->locale('id')->diffForHumans();
        }

        if ($election->isOpen() && $election->closes_at !== null) {
            return 'Ditutup '.$election->closes_at->locale('id')->diffForHumans();
        }

        return $election->opens_at !== null
            ? 'Dibuka '.$election->opens_at->locale('id')->diffForHumans()
            : null;
    }

    private static function formatSchedule(?CarbonInterface $schedule): ?string
    {
        return $schedule?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i');
    }
}
