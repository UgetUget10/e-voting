<?php

namespace App\Http\Controllers;

use App\Services\AdminActivityLog;
use App\Services\CsvCell;
use App\Services\ResultService;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultXlsxExportController extends Controller
{
    public function __invoke(Request $request, ResultService $results, AdminActivityLog $activityLog): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $activityLog->record('Mengekspor hasil voting ke Excel.');

        return response()->streamDownload(function () use ($results): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName('Hasil voting');
            $writer->addRow(Row::fromValues(['Bidang', 'Kandidat', 'Suara', 'Lolos']));

            foreach ($results->results() as $result) {
                foreach ($result['candidates']->sortByDesc('votes_count') as $candidate) {
                    $writer->addRow(Row::fromValues([
                        CsvCell::safe($result['field']->name),
                        CsvCell::safe($candidate->name),
                        (int) $candidate->votes_count,
                        $result['winners']->contains('id', $candidate->id) ? 'Ya' : 'Tidak',
                    ]));
                }
            }

            $writer->close();
        }, 'hasil-voting.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
