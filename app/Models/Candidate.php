<?php

namespace App\Models;

use App\Models\Concerns\RecordsAdminActivity;
use App\Services\ResultService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class Candidate extends Model
{
    use HasFactory, HasUuids, RecordsAdminActivity;

    /**
     * Civil-service ranks (golongan), lowest to highest, as written on official documents.
     *
     * @var list<string>
     */
    public const GOLONGAN = [
        'I/a', 'I/b', 'I/c', 'I/d',
        'II/a', 'II/b', 'II/c', 'II/d',
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    protected $fillable = ['name', 'position', 'photo_path', 'nip', 'golongan'];

    /**
     * A candidate who has been picked, or whose total was corrected, cannot be removed, since the votes and
     * corrections would go with it. Photos are stored on disk, so they are removed along with the candidate or when replaced.
     */
    protected static function booted(): void
    {
        static::deleting(function (Candidate $candidate): void {
            if ($candidate->hasRecordedVotes()) {
                throw new LogicException('Kandidat yang sudah dipilih tidak dapat dihapus.');
            }
        });

        static::updated(function (Candidate $candidate): void {
            $previousPhoto = $candidate->getOriginal('photo_path');

            if ($previousPhoto && $previousPhoto !== $candidate->photo_path) {
                Storage::disk('public')->delete($previousPhoto);
            }
        });

        static::deleted(function (Candidate $candidate): void {
            if ($candidate->photo_path) {
                Storage::disk('public')->delete($candidate->photo_path);
            }
        });

        static::saved(fn () => ResultService::forgetCache());
        static::deleted(fn () => ResultService::forgetCache());
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(VoteCorrection::class);
    }

    public function hasRecordedVotes(): bool
    {
        return $this->votes()->exists() || $this->corrections()->exists();
    }

    /**
     * The total across every field: counted picks plus the admin corrections.
     */
    public function voteTotal(): int
    {
        return $this->votes()->count() + (int) $this->corrections()->sum('amount');
    }

    /**
     * Adds the vote total and the corrections part of it, as vote_total and correction_total, to each candidate.
     */
    public function scopeWithVoteTotals(Builder $query): void
    {
        $query->select('candidates.*')->addSelect([
            DB::raw('(select count(*) from votes where votes.candidate_id = candidates.id) + (select coalesce(sum(amount), 0) from vote_corrections where vote_corrections.candidate_id = candidates.id) as vote_total'),
            DB::raw('(select coalesce(sum(amount), 0) from vote_corrections where vote_corrections.candidate_id = candidates.id) as correction_total'),
        ]);
    }

    /**
     * The fields this candidate has been picked in, each listed once.
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'votes', 'candidate_id', 'field_id')
            ->distinct()
            ->orderBy('fields.position')
            ->orderBy('fields.id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Whether the candidate has a photo, which is the only part of the profile voters see.
     */
    public function profileStatusLabel(): string
    {
        return filled($this->photo_path) ? 'Profil lengkap' : 'Belum ada foto';
    }

    /**
     * The order candidates are shown to voters and in results.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    protected function adminActivityLabel(): string
    {
        return 'kandidat';
    }

    protected function adminActivityName(): string
    {
        return (string) $this->name;
    }

    /**
     * @return list<string>
     */
    protected function adminActivityFields(): array
    {
        return ['name', 'nip', 'golongan', 'photo_path'];
    }
}
