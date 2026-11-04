<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Vote;
use App\Models\VoteCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoteCorrectionService
{
    /**
     * Set the correct total of one candidate in one field. The change from the count at this moment is stored as
     * a correction, so no ballot or pick is touched, and later ballots still add to the total.
     */
    public function correct(Candidate $candidate, Field $field, int $total, string $reason): VoteCorrection
    {
        $this->ensureNotLocked();

        if ($total < 0) {
            $this->fail('Total suara tidak boleh kurang dari 0.');
        }

        $current = $this->currentTotal($candidate, $field);

        if ($total === $current) {
            $this->fail('Total suara sudah '.$total.', tidak ada yang perlu dikoreksi.');
        }

        return DB::transaction(function () use ($candidate, $field, $total, $current, $reason): VoteCorrection {
            $correction = VoteCorrection::create([
                'candidate_id' => $candidate->getKey(),
                'field_id' => $field->getKey(),
                'user_id' => auth()->id(),
                'amount' => $total - $current,
                'reason' => $reason,
            ]);

            app(AdminActivityLog::class)->record(
                'Mengoreksi total suara "'.$candidate->name.'" di bidang "'.$field->name.'" dari '.$current.' menjadi '.$total.'. Alasan: '.$reason,
                ['total' => ['old' => $current, 'new' => $total]],
                notifyAdmins: true,
                subject: $candidate,
            );

            return $correction;
        });
    }

    /**
     * Undo a correction. The total may not drop below zero.
     */
    public function remove(VoteCorrection $correction): void
    {
        $this->ensureNotLocked();

        $candidate = $correction->candidate;
        $field = $correction->field;
        $current = $this->currentTotal($candidate, $field);

        if ($current - $correction->amount < 0) {
            $this->fail('Koreksi ini tidak bisa dibatalkan karena total suara akan menjadi negatif.');
        }

        DB::transaction(function () use ($correction, $candidate, $field, $current): void {
            $correction->delete();

            app(AdminActivityLog::class)->record(
                'Membatalkan koreksi suara "'.$candidate->name.'" di bidang "'.$field->name.'". Total dari '.$current.' menjadi '.($current - $correction->amount).'.',
                ['total' => ['old' => $current, 'new' => $current - $correction->amount]],
                notifyAdmins: true,
                subject: $candidate,
            );
        });
    }

    /**
     * The total of one candidate in one field: counted picks plus every correction.
     */
    public function currentTotal(Candidate $candidate, Field $field): int
    {
        $picks = Vote::query()
            ->where('candidate_id', $candidate->getKey())
            ->where('field_id', $field->getKey())
            ->count();

        $corrections = (int) VoteCorrection::query()
            ->where('candidate_id', $candidate->getKey())
            ->where('field_id', $field->getKey())
            ->sum('amount');

        return $picks + $corrections;
    }

    private function ensureNotLocked(): void
    {
        if (Election::isCurrentLocked()) {
            $this->fail('Data pemilihan sedang dikunci, koreksi suara tidak bisa diubah.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['total' => $message]);
    }
}
