<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserCsvImport
{
    public const MAX_ROWS = 500;

    public const MIN_PASSWORD_LENGTH = 8;

    private const GENERATED_PASSWORD_LENGTH = 10;

    /**
     * Import voters from a CSV file with a header row holding "nama" and "email" columns and, optionally,
     * "username" and "password". A new voter without a username gets one built from the front of the email, and a
     * username already taken by someone else makes the row invalid. Rows without a password get a random one,
     * returned in "generated" together with the username so the admin can hand them out once. Emails already in the
     * database (any letter case) or repeated in the file are skipped, and rows with a missing or invalid value are
     * counted as invalid. Existing voters keep their username. Voters are never created as admins. Everything is
     * imported in one transaction. With $dryRun the rows are checked and counted, then rolled back, so nothing is
     * saved and no passwords are returned.
     *
     * @return array{created: int, updated: int, skipped: int, invalid: int, truncated: bool, generated: list<array{name: string, email: string, username: string, password: string}>}
     */
    public function import(string $path, bool $updateExisting = false, bool $dryRun = false): array
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
            $emailColumn = $this->columnIndex($header, ['email']);
            $usernameColumn = $this->columnIndex($header, ['username']);
            $passwordColumn = $this->columnIndex($header, ['password']);

            if ($nameColumn === null || $emailColumn === null) {
                throw new InvalidArgumentException('Kolom "nama" dan "email" harus ada di baris pertama.');
            }

            if ($dryRun) {
                DB::beginTransaction();

                try {
                    $result = $this->importRows($handle, $delimiter, $nameColumn, $emailColumn, $usernameColumn, $passwordColumn, $updateExisting);
                } finally {
                    DB::rollBack();
                }

                return [...$result, 'generated' => []];
            }

            return DB::transaction(fn (): array => $this->importRows($handle, $delimiter, $nameColumn, $emailColumn, $usernameColumn, $passwordColumn, $updateExisting));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{created: int, updated: int, skipped: int, invalid: int, truncated: bool, generated: list<array{name: string, email: string, username: string, password: string}>}
     */
    private function importRows($handle, string $delimiter, int $nameColumn, int $emailColumn, ?int $usernameColumn, ?int $passwordColumn, bool $updateExisting): array
    {
        $known = User::query()->get(['id', 'email'])
            ->mapWithKeys(fn (User $user): array => [mb_strtolower($user->email) => $user->id])
            ->all();
        $knownUsernames = User::query()->whereNotNull('username')->get(['id', 'username'])
            ->mapWithKeys(fn (User $user): array => [mb_strtolower($user->username) => $user->id])
            ->all();
        $created = $updated = $skipped = $invalid = $rows = 0;
        $truncated = false;
        $generated = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            $name = trim((string) ($row[$nameColumn] ?? ''));
            $email = mb_strtolower(trim((string) ($row[$emailColumn] ?? '')));
            $username = $usernameColumn === null ? '' : mb_strtolower(trim((string) ($row[$usernameColumn] ?? '')));
            $password = $passwordColumn === null ? '' : trim((string) ($row[$passwordColumn] ?? ''));

            if ($name === '' || mb_strlen($name) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
                $invalid++;

                continue;
            }

            if ($password !== '' && mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $invalid++;

                continue;
            }

            if ($username !== '' && preg_match(User::USERNAME_PATTERN, $username) !== 1) {
                $invalid++;

                continue;
            }

            if (isset($known[$email])) {
                // Only voter accounts are ever updated from a file. Admin accounts are always skipped.
                $updateable = $updateExisting && User::query()->whereKey($known[$email])->where('is_admin', false)->exists();

                if (! $updateable) {
                    $skipped++;

                    continue;
                }

                $changes = ['name' => $name];

                if ($password !== '') {
                    $changes['password'] = Hash::make($password);
                }

                User::query()->whereKey($known[$email])->update($changes);
                $updated++;

                continue;
            }

            if ($username !== '' && isset($knownUsernames[$username])) {
                $invalid++;

                continue;
            }

            $username = $username !== '' ? $username : User::usernameFromEmail($email, $knownUsernames);

            if ($password === '') {
                $password = Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false);
                $generated[] = ['name' => $name, 'email' => $email, 'username' => $username, 'password' => $password];
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'password' => Hash::make($password),
                'is_admin' => false,
            ]);

            $known[$email] = $user->id;
            $knownUsernames[$username] = $user->id;
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'invalid' => $invalid,
            'truncated' => $truncated,
            'generated' => $generated,
        ];
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
