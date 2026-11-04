<?php

namespace App\Services;

use App\Models\Field;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FieldCsvImport
{
    public const MAX_ROWS = 100;

    /**
     * Import fields from a CSV file with a header row holding a "bidang" column (or "nama").
     * New fields are appended after the existing ones in file order. Names already in the database
     * (any letter case) or repeated in the file are skipped, and rows without a name are counted as invalid.
     * Comma and semicolon delimiters are both accepted. Everything is imported in one transaction.
     *
     * @return array{created: int, skipped: int, invalid: int, truncated: bool}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('File tidak bisa dibaca.');
        }

        try {
            $firstLine = (string) fgets($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = array_map(
                fn (?string $cell): string => mb_strtolower(trim(ltrim((string) $cell, "\xEF\xBB\xBF"))),
                str_getcsv($firstLine, $delimiter),
            );

            $nameColumn = array_search('bidang', $header, true);
            $nameColumn = $nameColumn === false ? array_search('nama', $header, true) : $nameColumn;

            if ($nameColumn === false) {
                throw new InvalidArgumentException('Kolom "bidang" tidak ditemukan di baris pertama.');
            }

            if ($dryRun) {
                return Field::withoutRecordingAdminActivity(function () use ($handle, $delimiter, $nameColumn): array {
                    DB::beginTransaction();

                    try {
                        return $this->importRows($handle, $delimiter, $nameColumn);
                    } finally {
                        DB::rollBack();
                    }
                });
            }

            return Field::withoutRecordingAdminActivity(
                fn (): array => DB::transaction(fn (): array => $this->importRows($handle, $delimiter, $nameColumn)),
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{created: int, skipped: int, invalid: int, truncated: bool}
     */
    private function importRows($handle, string $delimiter, int $nameColumn): array
    {
        $known = Field::query()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->flip()->all();
        $position = (int) Field::query()->max('position');
        $created = $skipped = $invalid = $rows = 0;
        $truncated = false;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            $name = trim((string) ($row[$nameColumn] ?? ''));

            if ($name === '' || mb_strlen($name) > 255) {
                $invalid++;

                continue;
            }

            if (isset($known[mb_strtolower($name)])) {
                $skipped++;

                continue;
            }

            Field::create(['name' => $name, 'position' => ++$position]);

            $known[mb_strtolower($name)] = true;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'invalid' => $invalid, 'truncated' => $truncated];
    }
}
