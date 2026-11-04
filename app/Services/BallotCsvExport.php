<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BallotCsvExport
{
    /**
     * Stream the given ballots as CSV, one row per pick, oldest ballot first. Pass the table's filtered query
     * so the export matches what the admin is looking at. Voter name and email are added only when
     * $includeVoters is true, which is for super admins; everyone else gets the receipt code alone.
     */
    public function stream(Builder $ballots, bool $includeVoters = false): StreamedResponse
    {
        $fileName = 'suara-pemilih-'.now('Asia/Jakarta')->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($ballots, $includeVoters): void {
            $handle = fopen('php://output', 'w');

            $header = ['Kode tanda terima', 'Waktu kirim', 'Bidang', 'Kandidat'];

            if ($includeVoters) {
                $header = ['Kode tanda terima', 'Nama pemilih', 'Email', 'Waktu kirim', 'Bidang', 'Kandidat'];
            }

            fputcsv($handle, $header);

            $ballots
                ->reorder('id')
                ->with(['votes.field', 'votes.candidate', ...($includeVoters ? ['user'] : [])])
                ->chunkById(200, function ($chunk) use ($handle, $includeVoters) {
                    foreach ($chunk as $ballot) {
                        /** @var Ballot $ballot */
                        foreach ($ballot->votes->sortBy(fn (Vote $vote) => [$vote->field->position, $vote->field->id]) as $vote) {
                            $row = [CsvCell::safe($ballot->receipt_code)];

                            if ($includeVoters) {
                                $row[] = CsvCell::safe($ballot->user->name);
                                $row[] = CsvCell::safe($ballot->user->email);
                            }

                            $row[] = $ballot->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s');
                            $row[] = CsvCell::safe($vote->field->name);
                            $row[] = CsvCell::safe($vote->candidate->name);

                            fputcsv($handle, $row);
                        }
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
