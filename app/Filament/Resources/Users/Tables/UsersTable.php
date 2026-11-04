<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Mail\VotingReminderMail;
use App\Models\Election;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountSuspension;
use App\Services\AdminActivityLog;
use App\Services\VoterCardIssuer;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsersTable
{
    /**
     * Emails are personal data. Only super admins see them; other admins manage voters by name.
     */
    public static function canSeeEmails(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return list<TextColumn>
     */
    private static function emailColumns(): array
    {
        if (! self::canSeeEmails()) {
            return [];
        }

        return [
            TextColumn::make('email')
                ->label('Email')
                ->color('gray')
                ->copyable()
                ->copyMessage('Email disalin.')
                ->searchable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withExists('ballot')
                ->with(['roles', 'ballot:id,user_id,created_at']))
            ->defaultSort('name')
            ->poll('15s')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (User $record): string => collect([
                'admin-user-row',
                $record->isSuspended() ? 'admin-user-suspended' : null,
                $record->ballot?->created_at?->gt(now()->subSeconds(45)) ? 'admin-row-enter' : null,
            ])->filter()->implode(' '))
            ->filters([
                TernaryFilter::make('voted')
                    ->label('Status memilih')
                    ->placeholder('Semua pemilih')
                    ->trueLabel('Sudah memilih')
                    ->falseLabel('Belum memilih')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('ballot'),
                        false: fn (Builder $query) => $query->whereDoesntHave('ballot'),
                    ),
                TernaryFilter::make('is_admin')
                    ->label('Akses admin')
                    ->placeholder('Semua akun')
                    ->trueLabel('Admin panel')
                    ->falseLabel('Pemilih biasa')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_admin', true),
                        false: fn (Builder $query) => $query->where('is_admin', false),
                    ),
                TernaryFilter::make('suspended')
                    ->label('Status akun')
                    ->placeholder('Semua akun')
                    ->trueLabel('Dinonaktifkan')
                    ->falseLabel('Aktif')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('suspended_at'),
                        false: fn (Builder $query) => $query->whereNull('suspended_at'),
                    ),
                SelectFilter::make('roles')
                    ->label('Peran')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => Str::headline($record->name))
                    ->multiple()
                    ->preload(),
                SelectFilter::make('registered')
                    ->label('Tanggal daftar')
                    ->placeholder('Kapan saja')
                    ->options([
                        'week' => 'Didaftarkan 7 hari terakhir',
                        'month' => 'Didaftarkan 30 hari terakhir',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'week' => $query->where('created_at', '>=', now()->subDays(7)),
                        'month' => $query->where('created_at', '>=', now()->subDays(30)),
                        default => $query,
                    }),
                Filter::make('registered_range')
                    ->label('Rentang tanggal daftar')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['from'] ?? null) && filled($data['until'] ?? null) => 'Daftar '.$data['from'].' s/d '.$data['until'],
                        filled($data['from'] ?? null) => 'Daftar sejak '.$data['from'],
                        filled($data['until'] ?? null) => 'Daftar sampai '.$data['until'],
                        default => null,
                    }),
            ])
            ->columns([
                ViewColumn::make('name')
                    ->label('Nama')
                    ->view('filament.users.name-cell')
                    ->searchable()
                    ->sortable(),
                ...self::emailColumns(),
                ViewColumn::make('roles')
                    ->label('Peran')
                    ->view('filament.users.role-chips'),
                TextColumn::make('ballot_exists')
                    ->label('Status memilih')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Sudah memilih' : 'Belum memilih')
                    ->color(fn (mixed $state): string => (bool) $state ? 'success' : 'gray')
                    ->icon(fn (mixed $state): Heroicon => (bool) $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock)
                    ->description(fn (User $record): ?string => $record->ballot
                        ? 'dikirim '.self::formatWib($record->ballot->created_at)
                        : null)
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('Terakhir masuk')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->diffForHumans())
                    ->description(fn (User $record): ?string => $record->last_login_at ? self::formatWib($record->last_login_at) : null)
                    ->placeholder('Belum pernah')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatWib($state, 'j M Y'))
                    ->sortable(),
                TextColumn::make('account_status')
                    ->label('Akun')
                    ->badge()
                    ->state(fn (User $record): string => $record->isSuspended() ? 'Dinonaktifkan' : 'Aktif')
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'danger')
                    ->icon(fn (string $state): Heroicon => $state === 'Aktif' ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedNoSymbol),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ActionGroup::make([
                        UserActions::changeEmail(),
                        UserActions::toggleSuspension(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        UserActions::resetPassword(),
                        UserActions::printCard(),
                    ])->dropdown(false),
                ])
                    ->label('Lainnya')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('issueCards')
                        ->label('Cetak kartu pemilih')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Cetak kartu pemilih')
                        ->modalDescription('Password pemilih terpilih akan diganti dengan password sementara acak dan ditampilkan sekali di kartu yang bisa dicetak. Akun admin dan akun yang dinonaktifkan dilewati.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, VoterCardIssuer $issuer, AdminActivityLog $activityLog) {
                            $count = $issuer->issue($records);

                            if ($count === 0) {
                                Notification::make()->warning()->title('Tidak ada pemilih yang dipilih.')->send();

                                return null;
                            }

                            $activityLog->record('Mencetak kartu pemilih dan mengatur ulang password '.$count.' pemilih.');

                            return redirect()->route('voter-cards.print');
                        }),
                    BulkAction::make('remindSelected')
                        ->label('Kirim pengingat')
                        ->icon(Heroicon::OutlinedBellAlert)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Kirim pengingat ke pemilih terpilih?')
                        ->modalDescription('Hanya pemilih yang belum memilih yang menerima email pengingat. Admin dan pemilih yang sudah memilih dilewati.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, AdminActivityLog $activityLog): void {
                            $election = Election::current();

                            if ($election === null || $election->hasClosed()) {
                                Notification::make()->warning()->title('Tidak ada voting yang berjalan.')->body('Pengingat hanya dikirim saat voting sedang dibuka.')->send();

                                return;
                            }

                            $targets = $records->reject(fn (User $user): bool => $user->is_admin || $user->ballot_exists || ! $user->email || $user->isSuspended());
                            $targets->each(fn (User $user) => Mail::to($user->email)->queue(new VotingReminderMail($user, $election)));

                            $activityLog->record('Mengirim pengingat ke '.$targets->count().' pemilih terpilih.');

                            Notification::make()->success()->title($targets->count().' pengingat masuk antrean.')->send();
                        }),
                    BulkAction::make('assignRoles')
                        ->label('Atur peran')
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->color('primary')
                        ->schema([
                            Select::make('roles')
                                ->label('Peran')
                                ->helperText('Peran dipilih akan ditambahkan ke pemilih terpilih. Peran yang sudah ada tidak dihapus.')
                                ->options(fn (): array => self::roleOptions())
                                ->multiple()
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data, AdminActivityLog $activityLog): void {
                            $authUser = auth()->user();
                            $targets = $records->reject(fn (User $user): bool => $user->is($authUser));
                            $roles = Role::query()->whereIn('id', $data['roles'])->get();

                            $targets->each(function (User $user) use ($roles): void {
                                $user->assignRole($roles);
                            });

                            $activityLog->record('Menambahkan peran '.$roles->pluck('name')->map(fn (string $role): string => Str::headline($role))->implode(', ').' ke '.$targets->count().' pemilih.');

                            Notification::make()
                                ->title($targets->count().' pemilih mendapat peran baru.')
                                ->body($targets->count() < $records->count() ? 'Akun sendiri dilewati karena peran akun sendiri tidak bisa diubah.' : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeRoles')
                        ->label('Cabut peran')
                        ->icon(Heroicon::OutlinedUserMinus)
                        ->color('warning')
                        ->schema([
                            Select::make('roles')
                                ->label('Peran yang dicabut')
                                ->helperText('Peran yang dipilih dicabut dari pemilih terpilih. Peran lain tidak ikut berubah.')
                                ->options(fn (): array => self::roleOptions())
                                ->multiple()
                                ->required()
                                ->searchable(),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Cabut peran dari pemilih terpilih?')
                        ->action(function (Collection $records, array $data, AdminActivityLog $activityLog): void {
                            $authUser = auth()->user();
                            $targets = $records->reject(fn (User $user): bool => $user->is($authUser));
                            $roles = Role::query()->whereIn('id', $data['roles'])->get();

                            $targets->each(fn (User $user) => $roles->each(fn (Role $role) => $user->removeRole($role)));

                            $activityLog->record('Mencabut peran '.$roles->pluck('name')->map(fn (string $role): string => Str::headline($role))->implode(', ').' dari '.$targets->count().' pemilih.');

                            Notification::make()
                                ->title('Peran dicabut dari '.$targets->count().' pemilih.')
                                ->body($targets->count() < $records->count() ? 'Akun sendiri dilewati karena peran akun sendiri tidak bisa diubah.' : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('suspendSelected')
                        ->label('Nonaktifkan akun')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan akun yang dipilih?')
                        ->modalDescription('Akun yang dinonaktifkan tidak bisa masuk ke panel mana pun. Suara dan riwayatnya tetap tersimpan. Akun sendiri dilewati.')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, AdminActivityLog $activityLog) => self::applySuspension($records, true, $activityLog)),
                    BulkAction::make('reactivateSelected')
                        ->label('Aktifkan kembali akun')
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Aktifkan kembali akun yang dipilih?')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, AdminActivityLog $activityLog) => self::applySuspension($records, false, $activityLog)),
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            $authUser = auth()->user();

                            if ($records->contains(fn (User $user): bool => $user->is($authUser) || $user->ballot()->exists())) {
                                Notification::make()
                                    ->danger()
                                    ->title('Pengguna ini tidak bisa dihapus.')
                                    ->body('Akun sendiri dan pemilih yang sudah memilih tidak bisa dihapus, karena menghapusnya juga menghapus suara.')
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Tambahkan pemilih satu per satu, atau impor dari CSV dengan tombol di atas.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Tambah pemilih')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->url(UserResource::getUrl('create'))
                    ->button()
                    ->visible(fn (): bool => UserResource::canCreate()),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => Str::headline($role->name)])
            ->all();
    }

    /**
     * @param  Collection<int, User>  $records
     */
    private static function applySuspension(Collection $records, bool $suspend, AdminActivityLog $activityLog): void
    {
        $result = app(AccountSuspension::class)->apply($records, $suspend, auth()->user());

        $activityLog->record(($suspend ? 'Menonaktifkan' : 'Mengaktifkan kembali').' '.$result['changed'].' akun dari daftar pengguna.', notifyAdmins: true);

        Notification::make()
            ->success()
            ->title($result['changed'].' akun '.($suspend ? 'dinonaktifkan.' : 'diaktifkan kembali.'))
            ->body($result['skipped'] > 0 ? $result['skipped'].' dilewati, karena akun sendiri atau sudah dalam keadaan itu.' : null)
            ->send();
    }

    private static function formatWib(?CarbonInterface $moment, string $format = 'j M Y, H:i'): ?string
    {
        return $moment?->timezone('Asia/Jakarta')->locale('id')->translatedFormat($format);
    }
}
