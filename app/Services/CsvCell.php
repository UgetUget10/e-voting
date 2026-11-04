<?php

namespace App\Services;

class CsvCell
{
    /**
     * Spreadsheet apps run a cell that starts with one of these characters as a formula,
     * so text from users and admins is prefixed with an apostrophe to keep it plain text.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
