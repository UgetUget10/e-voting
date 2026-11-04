<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CandidateNameSimilarity
{
    /**
     * Names shorter than this only match when they are equal after normalising, so short names such as "Ani"
     * and "Ana" are not flagged as duplicates of each other.
     */
    private const MIN_LENGTH_FOR_FUZZY_MATCH = 6;

    private const MAX_EDIT_DISTANCE = 2;

    /**
     * Other candidates whose name reads the same or almost the same, ignoring case, spacing and punctuation.
     *
     * @return Collection<int, Candidate>
     */
    public function similarTo(string $name, ?string $ignoreId = null): Collection
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return collect();
        }

        return Candidate::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'name'])
            ->filter(fn (Candidate $candidate): bool => $this->isSimilar($normalized, self::normalize($candidate->name)))
            ->values();
    }

    public static function normalize(string $name): string
    {
        $lettersAndDigits = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($name)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $lettersAndDigits) ?? '');
    }

    private function isSimilar(string $normalized, string $other): bool
    {
        if ($normalized === $other) {
            return true;
        }

        if (min(mb_strlen($normalized), mb_strlen($other)) < self::MIN_LENGTH_FOR_FUZZY_MATCH) {
            return false;
        }

        return levenshtein($normalized, $other) <= self::MAX_EDIT_DISTANCE;
    }
}
