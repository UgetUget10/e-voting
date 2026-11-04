<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Widgets\FieldStats;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Services\AdminActivityLog;
use App\Services\CsvCell;
use App\Services\CsvTemplate;
use App\Services\FieldCsvImport;
use App\Services\FieldStandings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListFields extends ListRecords
{
    protected static string $resource = FieldResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            FieldStats::class,
        ];
    }

    public function getSubheading(): ?string
    {
        return Election::isCurrentLocked()
            ? 'Data pemilihan sedang dikunci, jadi bidang tidak bisa ditambah, diubah, atau dihapus, dan urutannya tidak bisa diubah. Buka kunci di menu Pemilihan.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voterOrder')
                ->label('Pratinjau urutan pemilih')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading('Urutan langkah pemilih')
                ->modalDescription('Begini urutan yang dilihat pemilih saat memilih. Ubah urutan dengan menggeser baris di daftar bidang.')
                ->modalContent(fn () => view('filament.fields.voter-order', [
                    'fields' => Field::query()->ordered()->get(),
                    'candidateCount' => Candidate::count(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            ActionGroup::make([
                Action::make('import')
                    ->label('Impor CSV')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (): bool => FieldResource::canCreate())
                    ->modalHeading('Impor bidang dari CSV')
                    ->modalDescription('Baris pertama harus berisi kolom "bidang" (atau "nama"). Maksimal '.FieldCsvImport::MAX_ROWS.' baris. Bidang baru ditambahkan di urutan terakhir, dan nama yang sudah ada dilewati.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('File CSV')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->maxSize(512)
                            ->required(),
                        Toggle::make('dryRun')
                            ->label('Periksa dulu, jangan simpan')
                            ->helperText('Menampilkan berapa bidang yang akan dibuat atau dilewati tanpa mengubah data.')
                            ->default(false),
                    ])
                    ->action(function (array $data, FieldCsvImport $importer, AdminActivityLog $activityLog): void {
                        $path = Storage::disk('local')->path($data['file']);

                        try {
                            $result = $importer->import($path, (bool) ($data['dryRun'] ?? false));
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title('Impor gagal.')->body($exception->getMessage())->send();

                            return;
                        } finally {
                            Storage::disk('local')->delete($data['file']);
                        }

                        if ($data['dryRun'] ?? false) {
                            Notification::make()
                                ->info()
                                ->title('Pemeriksaan selesai, belum ada yang disimpan.')
                                ->body($result['created'].' bidang akan dibuat, '.$result['skipped'].' dilewati karena nama sudah ada, '.$result['invalid'].' baris tidak valid.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        $activityLog->record('Mengimpor '.$result['created'].' bidang dari CSV.');

                        Notification::make()
                            ->success()
                            ->title($result['created'].' bidang diimpor.')
                            ->body($result['skipped'].' dilewati karena nama sudah ada, '.$result['invalid'].' baris tidak valid.'.($result['truncated'] ? ' Hanya '.FieldCsvImport::MAX_ROWS.' baris pertama yang diproses.' : ''))
                            ->send();
                    }),
                Action::make('template')
                    ->label('Unduh template CSV')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->visible(fn (): bool => FieldResource::canCreate())
                    ->action(fn (): StreamedResponse => CsvTemplate::download('template-bidang.csv', ['bidang'])),
                Action::make('export')
                    ->label('Ekspor CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (FieldStandings $standings): StreamedResponse => $this->exportFields($standings)),
                Action::make('pdf')
                    ->label('Unduh PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(route('fields.export.pdf')),
            ])
                ->label('Impor & ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }

    private function exportFields(FieldStandings $standings): StreamedResponse
    {
        return response()->streamDownload(function () use ($standings): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Urutan', 'Bidang', 'Hasil', 'Pemimpin sementara', 'Suara pemimpin', 'Total suara']);

            foreach ($standings->all()->values() as $index => $standing) {
                fputcsv($handle, [
                    $index + 1,
                    CsvCell::safe($standing['field']->name),
                    FieldStandings::label($standing['status']),
                    $standing['leader'] ? CsvCell::safe($standing['leader']->name) : '',
                    $standing['leader']?->votes_count ?? 0,
                    $standing['total'],
                ]);
            }

            fclose($handle);
        }, 'daftar-bidang.csv', ['Content-Type' => 'text/csv']);
    }
}
