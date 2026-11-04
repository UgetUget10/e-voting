<?php

namespace App\Models\Concerns;

use App\Services\AdminActivityLog;
use Closure;

/**
 * Writes an admin activity entry when a signed-in admin creates, changes, or deletes the model.
 * Only the attributes listed by adminActivityFields() count as a change worth recording, and bulk
 * imports can switch recording off, since the import writes its own summary entry.
 */
trait RecordsAdminActivity
{
    private static bool $recordingAdminActivity = true;

    public static function withoutRecordingAdminActivity(Closure $callback): mixed
    {
        $previous = self::$recordingAdminActivity;
        self::$recordingAdminActivity = false;

        try {
            return $callback();
        } finally {
            self::$recordingAdminActivity = $previous;
        }
    }

    protected static function bootRecordsAdminActivity(): void
    {
        static::created(function (self $model): void {
            $model->recordAdminActivity('Menambahkan '.$model->adminActivityLabel().' "'.$model->adminActivityName().'".');
        });

        static::updated(function (self $model): void {
            if ($model->wasChanged($model->adminActivityFields())) {
                $changes = collect($model->adminActivityFields())
                    ->filter(fn (string $field): bool => $model->wasChanged($field))
                    ->mapWithKeys(fn (string $field): array => [$field => [
                        'old' => self::diffValue($model->getRawOriginal($field)),
                        'new' => self::diffValue($model->getAttributes()[$field] ?? null),
                    ]])
                    ->all();

                $model->recordAdminActivity('Mengubah '.$model->adminActivityLabel().' "'.$model->adminActivityName().'".', $changes);
            }
        });

        static::deleted(function (self $model): void {
            $model->recordAdminActivity('Menghapus '.$model->adminActivityLabel().' "'.$model->adminActivityName().'".');
        });
    }

    private static function diffValue(mixed $value): ?string
    {
        return $value === null ? null : mb_strimwidth((string) $value, 0, 300, '...');
    }

    private function recordAdminActivity(string $description, ?array $changes = null): void
    {
        if (! self::$recordingAdminActivity || ! auth()->check()) {
            return;
        }

        app(AdminActivityLog::class)->record($description, $changes);
    }

    abstract protected function adminActivityLabel(): string;

    abstract protected function adminActivityName(): string;

    /**
     * @return list<string>
     */
    abstract protected function adminActivityFields(): array;
}
