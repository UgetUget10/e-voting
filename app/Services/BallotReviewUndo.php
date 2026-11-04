<?php

namespace App\Services;

use App\Models\Ballot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BallotReviewUndo
{
    /**
     * How long after a review change the admin who made it can still take it back.
     */
    public const MINUTES = 5;

    /**
     * Keep each ballot's review flag as it was before a change, so the admin who made it can undo it.
     *
     * @param  array<int, bool>  $previous  The review flag before the change, keyed by ballot id.
     */
    public function remember(array $previous): string
    {
        $token = Str::random(40);

        Cache::put($this->key($token), [
            'user_id' => auth()->id(),
            'flags' => $previous,
        ], now()->addMinutes(self::MINUTES));

        return $token;
    }

    /**
     * Put the remembered flags back. Returns how many ballots were restored, or null when the token has
     * expired, was already used, or belongs to another admin.
     */
    public function restore(string $token, AdminActivityLog $activityLog): ?int
    {
        $remembered = Cache::get($this->key($token));

        if (! is_array($remembered) || $remembered['user_id'] !== auth()->id()) {
            return null;
        }

        Cache::forget($this->key($token));

        $restored = 0;

        foreach ([true, false] as $needsReview) {
            $ballotIds = array_keys(array_filter($remembered['flags'], fn (bool $flag): bool => $flag === $needsReview));

            $restored += Ballot::query()->whereKey($ballotIds)->update(['needs_review' => $needsReview]);
        }

        $activityLog->record('Membatalkan perubahan tanda tinjauan pada '.$restored.' surat suara.');

        return $restored;
    }

    private function key(string $token): string
    {
        return 'ballots.review-undo.'.$token;
    }
}
