<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PRIMARY_KEY_COLUMN = 'id';

    private const TEMPORARY_COLUMN = 'uuid_tmp';

    /**
     * Tables whose own primary key becomes a UUID. Framework tables such as jobs and migrations keep their
     * integer keys, because the queue and the migrator insert rows with an auto-increment id.
     *
     * @var list<string>
     */
    private const PRIMARY_KEY_TABLES = [
        'users', 'roles', 'permissions', 'elections', 'fields', 'candidates', 'ballots', 'votes',
        'vote_corrections', 'ballot_notes', 'admin_activities', 'settings',
    ];

    /**
     * Every column that points at a converted primary key. A morph column names its parent type in another column,
     * so each type is remapped only for its own rows.
     *
     * @var list<array{table: string, column: string, nullable: bool, parent?: string, morph?: array{type: string, map: array<string, string>}, foreign?: bool, onDelete?: ?string}>
     */
    private const REFERENCES = [
        ['table' => 'ballots', 'column' => 'user_id', 'nullable' => false, 'parent' => 'users', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'ballot_notes', 'column' => 'ballot_id', 'nullable' => false, 'parent' => 'ballots', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'ballot_notes', 'column' => 'user_id', 'nullable' => true, 'parent' => 'users', 'foreign' => true, 'onDelete' => 'null'],
        ['table' => 'admin_activities', 'column' => 'user_id', 'nullable' => true, 'parent' => 'users', 'foreign' => true, 'onDelete' => 'null'],
        ['table' => 'vote_corrections', 'column' => 'candidate_id', 'nullable' => false, 'parent' => 'candidates', 'foreign' => true, 'onDelete' => null],
        ['table' => 'vote_corrections', 'column' => 'field_id', 'nullable' => false, 'parent' => 'fields', 'foreign' => true, 'onDelete' => null],
        ['table' => 'vote_corrections', 'column' => 'user_id', 'nullable' => true, 'parent' => 'users', 'foreign' => true, 'onDelete' => 'null'],
        ['table' => 'votes', 'column' => 'ballot_id', 'nullable' => false, 'parent' => 'ballots', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'votes', 'column' => 'field_id', 'nullable' => false, 'parent' => 'fields', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'votes', 'column' => 'candidate_id', 'nullable' => false, 'parent' => 'candidates', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'model_has_roles', 'column' => 'role_id', 'nullable' => false, 'parent' => 'roles', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'model_has_roles', 'column' => 'model_id', 'nullable' => false, 'morph' => ['type' => 'model_type', 'map' => ['App\Models\User' => 'users']]],
        ['table' => 'model_has_permissions', 'column' => 'permission_id', 'nullable' => false, 'parent' => 'permissions', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'model_has_permissions', 'column' => 'model_id', 'nullable' => false, 'morph' => ['type' => 'model_type', 'map' => ['App\Models\User' => 'users']]],
        ['table' => 'role_has_permissions', 'column' => 'permission_id', 'nullable' => false, 'parent' => 'permissions', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'role_has_permissions', 'column' => 'role_id', 'nullable' => false, 'parent' => 'roles', 'foreign' => true, 'onDelete' => 'cascade'],
        ['table' => 'sessions', 'column' => 'user_id', 'nullable' => true, 'parent' => 'users'],
        ['table' => 'notifications', 'column' => 'notifiable_id', 'nullable' => false, 'morph' => ['type' => 'notifiable_type', 'map' => ['App\Models\User' => 'users']]],
        ['table' => 'admin_activities', 'column' => 'subject_id', 'nullable' => true, 'morph' => ['type' => 'subject_type', 'map' => [
            'App\Models\Ballot' => 'ballots',
            'App\Models\BallotNote' => 'ballot_notes',
            'App\Models\Candidate' => 'candidates',
            'App\Models\Election' => 'elections',
            'App\Models\Field' => 'fields',
            'App\Models\Setting' => 'settings',
            'App\Models\User' => 'users',
            'App\Models\VoteCorrection' => 'vote_corrections',
        ]]],
    ];

    /**
     * Indexes that include a converted column. They are dropped before the column changes and rebuilt afterwards.
     *
     * @var list<array{table: string, columns: list<string>, unique: bool}>
     */
    private const INDEXES = [
        ['table' => 'ballots', 'columns' => ['user_id'], 'unique' => true],
        ['table' => 'votes', 'columns' => ['ballot_id', 'field_id'], 'unique' => true],
        ['table' => 'vote_corrections', 'columns' => ['candidate_id', 'field_id'], 'unique' => false],
        ['table' => 'admin_activities', 'columns' => ['subject_type', 'subject_id'], 'unique' => false],
        ['table' => 'sessions', 'columns' => ['user_id'], 'unique' => false],
        ['table' => 'model_has_roles', 'columns' => ['model_id', 'model_type'], 'unique' => false],
        ['table' => 'model_has_permissions', 'columns' => ['model_id', 'model_type'], 'unique' => false],
        ['table' => 'notifications', 'columns' => ['notifiable_type', 'notifiable_id'], 'unique' => false],
    ];

    /**
     * Pivot tables whose composite primary key includes a converted column.
     *
     * @var array<string, list<string>>
     */
    private const PIVOT_PRIMARY_KEYS = [
        'model_has_roles' => ['role_id', 'model_id', 'model_type'],
        'model_has_permissions' => ['permission_id', 'model_id', 'model_type'],
        'role_has_permissions' => ['permission_id', 'role_id'],
    ];

    /**
     * Convert every integer key to a UUID, keeping the rows and their links intact.
     */
    public function up(): void
    {
        foreach (self::PRIMARY_KEY_TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->char(self::TEMPORARY_COLUMN, 36)->nullable());

            DB::table($table)->orderBy('id')->pluck('id')->each(
                fn (int|string $id) => DB::table($table)->where('id', $id)->update([self::TEMPORARY_COLUMN => (string) Str::uuid7()]),
            );
        }

        foreach (self::REFERENCES as $reference) {
            $this->addTemporaryColumn($reference['table'], $reference['column']);
            $this->remap($reference);
        }

        foreach (self::REFERENCES as $reference) {
            if (($reference['foreign'] ?? false) === true) {
                Schema::table($reference['table'], fn (Blueprint $blueprint) => $blueprint->dropForeign([$reference['column']]));
            }
        }

        foreach (self::INDEXES as $index) {
            Schema::table($index['table'], function (Blueprint $blueprint) use ($index): void {
                $index['unique'] ? $blueprint->dropUnique($index['columns']) : $blueprint->dropIndex($index['columns']);
            });
        }

        foreach (self::PIVOT_PRIMARY_KEYS as $table => $columns) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropPrimary());
        }

        foreach (self::REFERENCES as $reference) {
            Schema::table($reference['table'], fn (Blueprint $blueprint) => $blueprint->dropColumn($reference['column']));
            Schema::table($reference['table'], fn (Blueprint $blueprint) => $blueprint->renameColumn($this->temporary($reference['column']), $reference['column']));
            Schema::table($reference['table'], function (Blueprint $blueprint) use ($reference): void {
                $column = $blueprint->char($reference['column'], 36);

                $reference['nullable'] ? $column->nullable()->change() : $column->change();
            });
        }

        foreach (self::PRIMARY_KEY_TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger(self::PRIMARY_KEY_COLUMN)->change());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropPrimary());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(self::PRIMARY_KEY_COLUMN));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn(self::TEMPORARY_COLUMN, self::PRIMARY_KEY_COLUMN));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->char(self::PRIMARY_KEY_COLUMN, 36)->change());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->primary(self::PRIMARY_KEY_COLUMN));
        }

        foreach (self::INDEXES as $index) {
            Schema::table($index['table'], function (Blueprint $blueprint) use ($index): void {
                $index['unique'] ? $blueprint->unique($index['columns']) : $blueprint->index($index['columns']);
            });
        }

        foreach (self::PIVOT_PRIMARY_KEYS as $table => $columns) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->primary($columns));
        }

        foreach (self::REFERENCES as $reference) {
            if (($reference['foreign'] ?? false) !== true) {
                continue;
            }

            Schema::table($reference['table'], function (Blueprint $blueprint) use ($reference): void {
                $foreignKey = $blueprint->foreign($reference['column'])->references('id')->on($reference['parent']);

                match ($reference['onDelete']) {
                    'cascade' => $foreignKey->cascadeOnDelete(),
                    'null' => $foreignKey->nullOnDelete(),
                    default => $foreignKey,
                };
            });
        }
    }

    /**
     * Integer keys cannot be restored once rows carry UUIDs. Restore the database backup instead.
     */
    public function down(): void
    {
        throw new RuntimeException('Konversi kunci UUID tidak dapat dibalik secara otomatis. Pulihkan database dari cadangan.');
    }

    private function temporary(string $column): string
    {
        return $column.'_uuid_tmp';
    }

    private function addTemporaryColumn(string $table, string $column): void
    {
        if (Schema::hasColumn($table, $this->temporary($column))) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->char($this->temporary($column), 36)->nullable());
    }

    /**
     * Fill the temporary column with the parent's new UUID, and fail loudly if any row was left without one.
     *
     * @param  array{table: string, column: string, nullable: bool, parent?: string, morph?: array{type: string, map: array<string, string>}}  $reference
     */
    private function remap(array $reference): void
    {
        $table = $reference['table'];
        $column = $reference['column'];
        $temporary = $this->temporary($column);

        $pairs = isset($reference['morph'])
            ? $reference['morph']['map']
            : [null => $reference['parent']];

        foreach ($pairs as $type => $parent) {
            if (isset($reference['morph']) && ! $reference['nullable']) {
                // A pivot row or notification for a deleted record would have been removed by a cascade, so remove it now.
                DB::table($table)
                    ->where($reference['morph']['type'], $type)
                    ->whereNotIn($column, DB::table($parent)->select(self::PRIMARY_KEY_COLUMN))
                    ->delete();
            }

            $query = DB::table($table)->join($parent, "{$table}.{$column}", '=', "{$parent}.".self::PRIMARY_KEY_COLUMN);

            if (isset($reference['morph'])) {
                $query->where("{$table}.{$reference['morph']['type']}", $type);
            }

            $query->update(["{$table}.{$temporary}" => DB::raw("{$parent}.".self::TEMPORARY_COLUMN)]);
        }

        // A nullable link to a deleted record becomes NULL, the same result as ON DELETE SET NULL. A required link
        // with no parent means the data is already broken, so stop instead of guessing.
        if ($reference['nullable']) {
            return;
        }

        $unmapped = DB::table($table)->whereNotNull($column)->whereNull($temporary)->count();

        if ($unmapped > 0) {
            throw new RuntimeException("{$unmapped} baris di {$table}.{$column} tidak punya pasangan. Periksa data sebelum konversi.");
        }
    }
};
