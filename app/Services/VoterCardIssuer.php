<?php

namespace App\Services;

use App\Http\Controllers\VoterCardController;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VoterCardIssuer
{
    /**
     * Give each voter a fresh temporary password and put the cards in the session for printing. Admin accounts
     * and suspended accounts are skipped, since a card would not let them in. Returns how many cards were issued.
     *
     * @param  Collection<int, User>  $users
     */
    public function issue(Collection $users): int
    {
        $voters = $users->reject(fn (User $user): bool => $user->is_admin || $user->isSuspended())->values();

        if ($voters->isEmpty()) {
            return 0;
        }

        $cards = $voters->map(function (User $user): array {
            $password = Str::password(10, symbols: false);
            $user->update(['password' => $password]);

            return [
                'name' => $user->name,
                'email' => auth()->user()?->isSuperAdmin() ? $user->email : null,
                'password' => $password,
            ];
        })->all();

        session()->put(VoterCardController::SESSION_KEY, $cards);

        return count($cards);
    }
}
