<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BallotService
{
    /**
     * Number of different fields one candidate may be picked in.
     */
    public const MAX_FIELDS_PER_CANDIDATE = 2;

    /**
     * Submit a voter's ballot. $picks maps field id to candidate id. A voter
     * may pick in any number of fields, but one candidate may be picked in at
     * most MAX_FIELDS_PER_CANDIDATE fields. The ballot is final once stored.
     *
     * @param  array<int|string, int|string|null>  $picks
     */
    public function submit(User $user, array $picks): Ballot
    {
        $election = Election::current();

        if (! $election || ! $election->isOpen()) {
            $this->fail('Voting sedang tidak dibuka.');
        }

        if ($user->is_admin) {
            $this->fail('Akun admin tidak dapat melakukan voting.');
        }

        if ($user->ballot()->exists()) {
            $this->fail('Anda sudah melakukan voting dan tidak dapat mengubah pilihan.');
        }

        $picks = collect($picks)
            ->filter(fn ($candidateId) => filled($candidateId))
            ->map(fn ($candidateId, $fieldId) => [$fieldId, $candidateId]);

        if ($picks->isEmpty()) {
            $this->fail('Pilih minimal 1 kandidat.');
        }

        $pickedTimes = $picks->countBy(fn (array $pick) => $pick[1]);

        if ($pickedTimes->max() > self::MAX_FIELDS_PER_CANDIDATE) {
            $this->fail('Satu kandidat tidak boleh dipilih di lebih dari '.self::MAX_FIELDS_PER_CANDIDATE.' bidang.');
        }

        $candidates = Candidate::whereIn('id', $picks->pluck(1))->get()->keyBy('id');
        $fields = Field::whereIn('id', $picks->pluck(0))->get()->keyBy('id');

        if ($candidates->count() !== $pickedTimes->count() || $fields->count() !== $picks->count()) {
            $this->fail('Pilihan tidak valid.');
        }

        try {
            return DB::transaction(function () use ($user, $picks) {
                $ballot = Ballot::create(['user_id' => $user->id]);

                $ballot->votes()->createMany(
                    $picks->map(fn (array $pick) => [
                        'field_id' => $pick[0],
                        'candidate_id' => $pick[1],
                    ])->all()
                );

                return $ballot;
            });
        } catch (QueryException $exception) {
            // The unique index on ballots.user_id rejects a concurrent second submission.
            if (! $user->ballot()->exists()) {
                throw $exception;
            }

            $this->fail('Anda sudah melakukan voting dan tidak dapat mengubah pilihan.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['candidates' => $message]);
    }
}
