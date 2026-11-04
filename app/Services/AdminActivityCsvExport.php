<?php

namespace App\Services;

use App\Models\AdminActivity;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminActivityCsvExport
{
    /**
     * Stream the given activity entries as CSV, newest first. Pass the table's filtered query so the
     * export matches what the admin is looking at.
     */
    public function stream(Builder $activities): StreamedResponse
    {
        return response()->streamDownload(function () use ($activities): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Waktu', 'Admin', 'Kategori', 'Penting', 'Tindakan', 'Jenis data', 'Perubahan']);

            $activities
                ->reorder('id', 'desc')
                ->with('user')
                ->chunkById(200, function ($chunk) use ($handle): void {
                    foreach ($chunk as $activity) {
                        fputcsv($handle, [
                            $activity->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                            CsvCell::safe($activity->user?->name ?? 'Sistem'),
                            AdminActivityClassifier::categoryLabel($activity->category),
                            $activity->sensitive ? 'Ya' : 'Tidak',
                            CsvCell::safe($activity->description),
                            AdminActivityClassifier::subjectLabel($activity->subject_type) ?? '',
                            CsvCell::safe(self::changesText($activity)),
                        ]);
                    }
                }, 'id', 'id');

            fclose($handle);
        }, 'log-aktivitas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * "Nama: Andi → Bunga; Golongan: — → III/a", so one cell holds every change of the entry.
     */
    private static function changesText(AdminActivity $activity): string
    {
        return collect($activity->changes ?? [])
            ->map(fn (array $change, string $field): string => AdminActivityClassifier::fieldLabel($field)
                .': '.AdminActivityClassifier::formatChangeValue($field, $change['old'] ?? null)
                .' → '.AdminActivityClassifier::formatChangeValue($field, $change['new'] ?? null))
            ->implode('; ');
    }
}
