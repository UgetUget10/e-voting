<?php

namespace App\Services;

use App\Models\AdminActivity;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLog
{
    /**
     * Record one administrative action with the admin who did it. Runs without a signed-in user too,
     * in which case the entry has no author. $changes maps a column to its old and new value.
     *
     * $notifyAdmins also sends the description to every other active admin's notification bell. Use it
     * for actions that change what voters see or what the election is doing. $subject links the entry to
     * the record it is about, so that record can show its own history.
     *
     * @param  array<string, array{old: mixed, new: mixed}>|null  $changes
     */
    public function record(string $description, ?array $changes = null, bool $notifyAdmins = false, ?Model $subject = null): AdminActivity
    {
        $classifier = app(AdminActivityClassifier::class);

        $activity = AdminActivity::create([
            'user_id' => auth()->id(),
            'description' => $description,
            'category' => $classifier->category($description),
            'sensitive' => $classifier->isSensitive($description),
            'changes' => $changes,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        if ($notifyAdmins) {
            $this->notifyOtherAdmins($description);
        }

        return $activity;
    }

    private function notifyOtherAdmins(string $description): void
    {
        $actor = auth()->user();

        $admins = User::query()
            ->where('is_admin', true)
            ->whereNull('suspended_at')
            ->when($actor, fn (Builder $query): Builder => $query->whereKeyNot($actor->getKey()))
            ->get();

        foreach ($admins as $admin) {
            Notification::make()
                ->title($description)
                ->body($actor ? 'Dilakukan oleh '.$actor->name.'.' : 'Dilakukan oleh sistem.')
                ->warning()
                ->sendToDatabase($admin);
        }
    }
}
