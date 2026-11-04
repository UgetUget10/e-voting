<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AccountSuspension
{
    /**
     * Suspend or reactivate accounts. The acting admin's own account is always left alone, so an admin can
     * never lock themselves out. Accounts already in the requested state are not counted as changed.
     *
     * @param  Collection<int, User>  $users
     * @return array{changed: int, skipped: int}
     */
    public function apply(Collection $users, bool $suspend, User $actor): array
    {
        $own = $users->filter(fn (User $user): bool => $user->is($actor));
        $others = $users->reject(fn (User $user): bool => $user->is($actor));

        $changing = $others->filter(fn (User $user): bool => $suspend ? ! $user->isSuspended() : $user->isSuspended());

        $changing->each(function (User $user) use ($suspend): void {
            $user->update(['suspended_at' => $suspend ? now() : null]);
        });

        return [
            'changed' => $changing->count(),
            'skipped' => $own->count() + ($others->count() - $changing->count()),
        ];
    }
}
