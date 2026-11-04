<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvTemplate
{
    /**
     * A CSV with only the header row, so the columns an import expects can be filled in without a sample row
     * that could be imported by mistake.
     *
     * @param  list<string>  $columns
     */
    public static function download(string $filename, array $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columns);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
