<?php

namespace App\Http\Controllers;

use App\Services\AdminActivityLog;
use App\Services\CsvCell;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultExportController extends Controller
{
    public function __invoke(Request $request, ResultService $results, AdminActivityLog $activityLog): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $activityLog->record('Mengekspor hasil voting ke CSV.');

        return response()->streamDownload(function () use ($results) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Bidang', 'Kandidat', 'Suara', 'Lolos']);

            foreach ($results->results() as $result) {
                foreach ($result['candidates']->sortByDesc('votes_count') as $candidate) {
                    fputcsv($handle, [
                        CsvCell::safe($result['field']->name),
                        CsvCell::safe($candidate->name),
                        $candidate->votes_count,
                        $result['winners']->contains('id', $candidate->id) ? 'Ya' : 'Tidak',
                    ]);
                }
            }

            fclose($handle);
        }, 'hasil-voting.csv', ['Content-Type' => 'text/csv']);
    }
}
