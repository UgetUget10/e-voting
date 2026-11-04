<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Widgets\CandidateStats;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Services\AdminActivityLog;
use App\Services\CandidateCsvImport;
use App\Services\CandidatePhotoImport;
use App\Services\CsvCell;
use App\Services\CsvTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    /**
     * 'table' or 'grid'. Kept in the URL so a reload or a shared link opens the same layout.
     */
    #[Url(as: 'tampilan')]
    public string $displayMode = 'table';

    protected function getHeaderWidgets(): array
    {
        return [
            CandidateStats::class,
        ];
    }

    public function getSubheading(): ?string
    {
        return Election::isCurrentLocked()
            ? 'Data pemilihan sedang dikunci, jadi kandidat tidak bisa ditambah, diubah, atau dihapus. Buka kunci di menu Pemilihan.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('layout')
                ->label(fn (): string => $this->displayMode === 'grid' ? 'Tampilan tabel' : 'Tampilan kartu')
                ->icon(fn (): Heroicon => $this->displayMode === 'grid' ? Heroicon::OutlinedTableCells : Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->action(function (): void {
                    $this->displayMode = $this->displayMode === 'grid' ? 'table' : 'grid';
                }),
            Action::make('shuffle')
                ->label('Acak urutan')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->visible(fn (): bool => ! Election::isCurrentLocked())
                ->requiresConfirmation()
                ->modalHeading('Acak urutan kandidat?')
                ->modalDescription('Urutan yang dilihat pemilih dan di hasil akan diacak. Urutan sebelumnya tidak disimpan.')
                ->action(function (AdminActivityLog $activityLog): void {
                    abort_if(Election::isCurrentLocked(), 403);

                    $ids = Candidate::query()->inRandomOrder()->pluck('id');

                    DB::transaction(function () use ($ids): void {
                        foreach ($ids as $index => $id) {
                            Candidate::query()->whereKey($id)->update(['position' => $index + 1]);
                        }
                    });

                    $activityLog->record('Mengacak urutan '.$ids->count().' kandidat.', notifyAdmins: true);

                    Notification::make()->title('Urutan '.$ids->count().' kandidat diacak.')->success()->send();
                }),
            ActionGroup::make([
                Action::make('import')
                    ->label('Impor CSV')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->visible(fn (): bool => CandidateResource::canCreate())
                    ->modalHeading('Impor kandidat dari CSV')
                    ->modalDescription('Baris pertama harus berisi nama kolom: "nama", dan boleh ditambah "nip" dan "golongan". Maksimal '.CandidateCsvImport::MAX_ROWS.' baris. Nama yang sudah ada dilewati, dan baris dengan NIP yang sudah dipakai atau golongan yang tidak ada di daftar dianggap tidak valid.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('File CSV')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->maxSize(512)
                            ->required(),
                    ])
                    ->action(function (array $data, CandidateCsvImport $importer, AdminActivityLog $activityLog): void {
                        $path = Storage::disk('local')->path($data['file']);

                        try {
                            $result = $importer->import($path);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title('Impor gagal.')->body($exception->getMessage())->send();

                            return;
                        } finally {
                            Storage::disk('local')->delete($data['file']);
                        }

                        $activityLog->record('Mengimpor '.$result['created'].' kandidat dari CSV.');

                        Notification::make()
                            ->success()
                            ->title($result['created'].' kandidat diimpor.')
                            ->body($result['skipped'].' dilewati karena nama sudah ada, '.$result['invalid'].' baris tidak valid.'.($result['truncated'] ? ' Hanya '.CandidateCsvImport::MAX_ROWS.' baris pertama yang diproses.' : ''))
                            ->send();
                    }),
                Action::make('template')
                    ->label('Unduh template CSV')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->visible(fn (): bool => CandidateResource::canCreate())
                    ->action(fn (): StreamedResponse => CsvTemplate::download('template-kandidat.csv', ['nama', 'nip', 'golongan'])),
                Action::make('photos')
                    ->label('Unggah foto massal')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('gray')
                    ->visible(fn (): bool => ! Election::isCurrentLocked())
                    ->modalHeading('Unggah foto massal')
                    ->modalDescription('Beri nama setiap file sesuai nama kandidat, misalnya "andi-pratama.jpg". Foto yang sudah ada akan diganti. Maksimal '.CandidatePhotoImport::MAX_FILES.' file.')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Foto kandidat')
                            ->disk('local')
                            ->directory('imports')
                            ->preserveFilenames()
                            ->image()
                            ->multiple()
                            ->maxFiles(CandidatePhotoImport::MAX_FILES)
                            ->maxSize(4096)
                            ->required(),
                    ])
                    ->action(function (array $data, CandidatePhotoImport $importer, AdminActivityLog $activityLog): void {
                        $paths = array_map(fn (string $file): string => Storage::disk('local')->path($file), $data['files']);

                        try {
                            $result = $importer->import($paths);
                        } finally {
                            Storage::disk('local')->delete($data['files']);
                        }

                        $activityLog->record('Mengunggah '.$result['matched'].' foto kandidat sekaligus.');

                        $notification = Notification::make()
                            ->success()
                            ->title($result['matched'].' foto dipasang.');

                        if ($result['unmatched'] !== []) {
                            $notification
                                ->warning()
                                ->title($result['matched'].' foto dipasang, '.count($result['unmatched']).' file tidak cocok.')
                                ->body('Tidak cocok dengan nama kandidat: '.implode(', ', $result['unmatched']));
                        }

                        $notification->send();
                    }),
                Action::make('export')
                    ->label('Ekspor CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->exportCandidates()),
                Action::make('pdf')
                    ->label('Unduh PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->url(route('candidates.export.pdf')),
            ])
                ->label('Impor & ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }

    private function exportCandidates(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Urutan', 'Nama kandidat', 'NIP', 'Golongan', 'Punya foto', 'Bidang terpilih', 'Total suara']);

            $candidates = Candidate::query()
                ->ordered()
                ->withVoteTotals()
                ->addSelect([
                    'fields_count' => Vote::query()
                        ->selectRaw('count(distinct field_id)')
                        ->whereColumn('candidate_id', 'candidates.id'),
                ])
                ->get();

            foreach ($candidates->values() as $index => $candidate) {
                fputcsv($handle, [
                    $index + 1,
                    CsvCell::safe($candidate->name),
                    CsvCell::safe($candidate->nip ?? ''),
                    CsvCell::safe($candidate->golongan ?? ''),
                    filled($candidate->photo_path) ? 'Ya' : 'Tidak',
                    $candidate->fields_count,
                    $candidate->vote_total,
                ]);
            }

            fclose($handle);
        }, 'daftar-kandidat.csv', ['Content-Type' => 'text/csv']);
    }
}
