<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\UserStats;
use App\Mail\VotingReminderMail;
use App\Models\Election;
use App\Models\User;
use App\Services\AdminActivityLog;
use App\Services\CsvCell;
use App\Services\CsvTemplate;
use App\Services\UserCsvImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            UserStats::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->icon(Heroicon::OutlinedUsers)
                ->badge(User::query()->count()),
            'not_voted' => Tab::make('Belum memilih')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_admin', false)->whereDoesntHave('ballot'))
                ->badge(User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count()),
            'voted' => Tab::make('Sudah memilih')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('ballot'))
                ->badge(User::query()->whereHas('ballot')->count()),
            'admins' => Tab::make('Admin panel')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_admin', true))
                ->badge(User::query()->where('is_admin', true)->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('remind')
                ->label('Kirim pengingat')
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('warning')
                ->visible(fn (): bool => User::query()->where('is_admin', false)->whereDoesntHave('ballot')->whereNotNull('email')->exists())
                ->requiresConfirmation()
                ->modalHeading('Kirim pengingat ke pemilih yang belum memilih?')
                ->modalDescription(fn (): string => 'Pengingat dikirim ke '.User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count().' pemilih lewat email, dan masuk antrean. Pastikan antrean (queue worker) sedang berjalan.')
                ->modalSubmitActionLabel('Kirim pengingat')
                ->action(function (AdminActivityLog $activityLog): void {
                    $election = Election::current();

                    if ($election === null || $election->hasClosed()) {
                        Notification::make()->warning()->title('Tidak ada voting yang berjalan.')->body('Pengingat hanya dikirim saat voting sedang dibuka.')->send();

                        return;
                    }

                    $count = 0;

                    User::query()
                        ->where('is_admin', false)
                        ->whereDoesntHave('ballot')
                        ->orderBy('id')
                        ->chunkById(200, function ($voters) use ($election, &$count): void {
                            foreach ($voters as $voter) {
                                Mail::to($voter->email)->queue(new VotingReminderMail($voter, $election));
                                $count++;
                            }
                        });

                    $activityLog->record('Mengirim pengingat ke '.$count.' pemilih yang belum memilih.');

                    Notification::make()->success()->title($count.' pengingat masuk antrean.')->send();
                }),
            Action::make('import')
                ->label('Impor CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Impor pemilih dari CSV')
                ->modalDescription('Baris pertama harus berisi kolom "nama" dan "email", dan boleh ditambah "password" (minimal '.UserCsvImport::MIN_PASSWORD_LENGTH.' karakter). Pemilih tanpa password diberi password acak yang bisa diunduh setelah impor. Maksimal '.UserCsvImport::MAX_ROWS.' baris, dan email yang sudah ada dilewati.')
                ->schema([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(512)
                        ->required(),
                    Toggle::make('updateExisting')
                        ->label('Perbarui pemilih yang sudah ada')
                        ->helperText('Nama dan, bila diisi, password pemilih dengan email yang sama ikut diperbarui. Akun admin tidak pernah diubah.')
                        ->default(false),
                    Toggle::make('dryRun')
                        ->label('Periksa dulu, jangan simpan')
                        ->helperText('Menampilkan berapa pemilih yang akan dibuat, dilewati, atau tidak valid tanpa mengubah data.')
                        ->default(false),
                ])
                ->action(function (array $data, UserCsvImport $importer, AdminActivityLog $activityLog): ?StreamedResponse {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $result = $importer->import($path, (bool) ($data['updateExisting'] ?? false), (bool) ($data['dryRun'] ?? false));
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->danger()->title('Impor gagal.')->body($exception->getMessage())->send();

                        return null;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    if ($data['dryRun'] ?? false) {
                        Notification::make()
                            ->info()
                            ->title('Pemeriksaan selesai, belum ada yang disimpan.')
                            ->body($result['created'].' pemilih akan dibuat, '.($result['updated'] > 0 ? $result['updated'].' diperbarui, ' : '').$result['skipped'].' dilewati karena email sudah ada, '.$result['invalid'].' baris tidak valid.'.($result['truncated'] ? ' Hanya '.UserCsvImport::MAX_ROWS.' baris pertama yang diproses.' : ''))
                            ->persistent()
                            ->send();

                        return null;
                    }

                    $generated = count($result['generated']);
                    $activityLog->record('Mengimpor '.$result['created'].' pemilih dari CSV.');

                    Notification::make()
                        ->success()
                        ->title($result['created'].' pemilih diimpor.')
                        ->body(($result['updated'] > 0 ? $result['updated'].' pemilih diperbarui, ' : '').$result['skipped'].' dilewati karena email sudah ada, '.$result['invalid'].' baris tidak valid.'
                            .($generated > 0 ? ' '.$generated.' pemilih mendapat password acak. Unduh daftarnya sekarang, karena password tidak disimpan di server.' : '')
                            .($result['truncated'] ? ' Hanya '.UserCsvImport::MAX_ROWS.' baris pertama yang diproses.' : ''))
                        ->persistent()
                        ->send();

                    return $generated > 0 ? $this->downloadGeneratedPasswords($result['generated']) : null;
                }),
            Action::make('template')
                ->label('Unduh template CSV')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(fn (): StreamedResponse => CsvTemplate::download('template-pemilih.csv', ['nama', 'email', 'password'])),
            Action::make('export')
                ->label('Ekspor pemilih')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportUsers()),
            CreateAction::make(),
        ];
    }

    /**
     * @param  list<array{name: string, email: string, password: string}>  $accounts
     */
    private function downloadGeneratedPasswords(array $accounts): StreamedResponse
    {
        return response()->streamDownload(function () use ($accounts): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Nama', 'Email', 'Password awal']);

            foreach ($accounts as $account) {
                fputcsv($handle, [
                    CsvCell::safe($account['name']),
                    CsvCell::safe($account['email']),
                    CsvCell::safe($account['password']),
                ]);
            }

            fclose($handle);
        }, 'password-pemilih-baru.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportUsers(): StreamedResponse
    {
        $includeEmails = auth()->user()?->isSuperAdmin() ?? false;

        return response()->streamDownload(function () use ($includeEmails) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $includeEmails
                ? ['Nama', 'Email', 'Peran', 'Sudah memilih', 'Admin panel']
                : ['Nama', 'Peran', 'Sudah memilih', 'Admin panel']);

            User::query()
                ->withExists('ballot')
                ->with('roles')
                ->orderBy('name')
                ->chunk(200, function ($users) use ($handle, $includeEmails) {
                    foreach ($users as $user) {
                        $row = [CsvCell::safe($user->name)];

                        if ($includeEmails) {
                            $row[] = CsvCell::safe($user->email);
                        }

                        $row[] = CsvCell::safe($user->roles->pluck('name')->map(fn (string $role): string => Str::headline($role))->implode(', '));
                        $row[] = $user->ballot_exists ? 'Ya' : 'Tidak';
                        $row[] = $user->is_admin ? 'Ya' : 'Tidak';

                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, 'daftar-pemilih.csv', ['Content-Type' => 'text/csv']);
    }
}
