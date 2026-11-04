<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CandidateCsvImport
{
    public const MAX_ROWS = 200;

    /**
     * Import candidates from a CSV file with a header row holding a "nama" column and, optionally, "nip" and
     * "golongan". Comma and semicolon delimiters are both accepted. Names already in the database (any letter case)
     * or repeated in the file are skipped. Rows without a usable name, with an over-long name, with a NIP that is
     * not digits or is already used by another candidate, or with a golongan that is not on the list are counted
     * as invalid. Everything is imported in one transaction.
     *
     * @return array{created: int, skipped: int, invalid: int, truncated: bool}
     */
    public function import(string $path): array
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

            $nameColumn = $this->columnIndex($header, ['nama', 'name']);
            $nipColumn = $this->columnIndex($header, ['nip']);
            $golonganColumn = $this->columnIndex($header, ['golongan']);

            if ($nameColumn === null) {
                throw new InvalidArgumentException('Kolom "nama" tidak ditemukan di baris pertama.');
            }

            return Candidate::withoutRecordingAdminActivity(
                fn (): array => DB::transaction(fn (): array => $this->importRows($handle, $delimiter, $nameColumn, $nipColumn, $golonganColumn)),
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{created: int, skipped: int, invalid: int, truncated: bool}
     */
    private function importRows($handle, string $delimiter, int $nameColumn, ?int $nipColumn, ?int $golonganColumn): array
    {
        $known = Candidate::query()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->flip()->all();
        $ownerOfNip = Candidate::query()->whereNotNull('nip')->pluck('nip')->flip()->all();
        $position = (int) Candidate::query()->max('position');
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
            $nip = $this->normalizeNip($this->cell($row, $nipColumn));
            $golongan = $this->normalizeGolongan($this->cell($row, $golonganColumn));

            if ($name === '' || mb_strlen($name) > 255 || $nip === false || $golongan === false) {
                $invalid++;

                continue;
            }

            if (isset($known[mb_strtolower($name)])) {
                $skipped++;

                continue;
            }

            if ($nip !== null && isset($ownerOfNip[$nip])) {
                $invalid++;

                continue;
            }

            Candidate::create([
                'name' => $name,
                'nip' => $nip,
                'golongan' => $golongan,
                'position' => ++$position,
            ]);

            $known[mb_strtolower($name)] = true;

            if ($nip !== null) {
                $ownerOfNip[$nip] = true;
            }

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'invalid' => $invalid, 'truncated' => $truncated];
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function cell(array $row, ?int $index): string
    {
        return $index === null ? '' : trim((string) ($row[$index] ?? ''));
    }

    /**
     * Digits only, with spaces dropped. Null when the cell is empty; false when it is not a valid NIP.
     */
    private function normalizeNip(string $value): string|false|null
    {
        $digits = str_replace(' ', '', $value);

        if ($digits === '') {
            return null;
        }

        return preg_match('/^\d{1,30}$/', $digits) === 1 ? $digits : false;
    }

    /**
     * The golongan as written in the list (so "iii/a" becomes "III/a"). Null when empty; false when not on the list.
     */
    private function normalizeGolongan(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }

        foreach (Candidate::GOLONGAN as $golongan) {
            if (strcasecmp($golongan, $value) === 0) {
                return $golongan;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $names
     */
    private function columnIndex(array $header, array $names): ?int
    {
        foreach ($header as $index => $cell) {
            if (in_array($cell, $names, true)) {
                return $index;
            }
        }

        return null;
    }
}
