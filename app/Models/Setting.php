<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single institution-wide value, looked up by its key. Settings are not per election, so they stay
 * the same when a new election is created.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, HasUuids;

    public const INSTITUTION_NAME = 'institution_name';

    public const SIGNER_NAME = 'signer_name';

    public const SIGNER_TITLE = 'signer_title';

    public const VOTER_ANNOUNCEMENT = 'voter_announcement';

    /**
     * @return array<string, string|null>
     */
    public static function valuesFor(array $keys): array
    {
        $stored = static::query()->whereIn('key', $keys)->pluck('value', 'key');

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $stored->get($key)])->all();
    }

    public static function value(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return filled($value) ? $value : $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
