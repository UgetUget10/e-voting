<?php

namespace App\Models;

use App\Models\Concerns\RecordsAdminActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Election extends Model
{
    use HasFactory, HasUuids, RecordsAdminActivity;

    protected $fillable = ['name', 'opens_at', 'closes_at', 'show_results_early', 'locked_at', 'archived_at', 'results_snapshot'];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'show_results_early' => 'boolean',
            'locked_at' => 'datetime',
            'archived_at' => 'datetime',
            'results_snapshot' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->whereNull('archived_at')->latest('id')->first();
    }

    /**
     * Whether the current election is locked. A locked election freezes the candidate, field, and voter
     * data so nothing changes while voting runs.
     */
    public static function isCurrentLocked(): bool
    {
        return static::current()?->isLocked() ?? false;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isOpen(): bool
    {
        $now = now();

        return ($this->opens_at === null || $now->gte($this->opens_at))
            && ($this->closes_at === null || $now->lt($this->closes_at));
    }

    public function hasClosed(): bool
    {
        return $this->closes_at !== null && now()->gte($this->closes_at);
    }

    /**
     * Where voting stands right now: before it opens, open for ballots, or already closed.
     */
    public function votingStatus(): string
    {
        return match (true) {
            $this->isOpen() => 'open',
            $this->hasClosed() => 'closed',
            default => 'upcoming',
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->hasClosed() => 'Ditutup',
            $this->isOpen() => 'Dibuka',
            default => 'Belum dibuka',
        };
    }

    public function scopeOpen(Builder $query): void
    {
        $now = now();

        $query->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', $now));
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closes_at')->where('closes_at', '<=', now());
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereNotNull('opens_at')
            ->where('opens_at', '>', now())
            ->whereNot(fn (Builder $query) => $query->closed());
    }

    /**
     * Elections whose voting starts in the current Jakarta week (Monday to Sunday).
     */
    public function scopeOpensThisWeek(Builder $query): void
    {
        $startOfWeek = now('Asia/Jakarta')->startOfWeek();
        $endOfWeek = now('Asia/Jakarta')->endOfWeek();

        $query->whereBetween('opens_at', [$startOfWeek->utc(), $endOfWeek->utc()]);
    }

    /**
     * Elections missing either the opening or the closing time, which the voters' window needs.
     */
    public function scopeScheduleIncomplete(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhereNull('closes_at'));
    }

    /**
     * Whether voting is open and closes within the given number of minutes.
     */
    public function closesWithin(int $minutes): bool
    {
        return $this->isOpen()
            && $this->closes_at !== null
            && $this->closes_at->lte(now()->addMinutes($minutes));
    }

    /**
     * Percentage of the voting window that has passed, or null when either end of the window is not set.
     */
    public function windowProgress(): ?int
    {
        if ($this->opens_at === null || $this->closes_at === null) {
            return null;
        }

        $total = max(1, $this->closes_at->timestamp - $this->opens_at->timestamp);
        $elapsed = now()->timestamp - $this->opens_at->timestamp;

        return (int) min(100, max(0, round($elapsed / $total * 100)));
    }

    /**
     * Other elections whose voting window overlaps this one. A missing opens_at or closes_at
     * means the window has no limit on that side.
     *
     * @return Collection<int, self>
     */
    public function overlappingElections(): Collection
    {
        return static::query()
            ->whereKeyNot($this->getKey())
            ->get()
            ->filter(fn (self $other): bool => ($this->closes_at === null || $other->opens_at === null || $other->opens_at->lt($this->closes_at))
                && ($other->closes_at === null || $this->opens_at === null || $this->opens_at->lt($other->closes_at)));
    }

    /**
     * Results are shown once voting closes, or earlier if an admin opts in.
     */
    public function resultsVisible(): bool
    {
        return $this->show_results_early || $this->hasClosed();
    }

    protected function adminActivityLabel(): string
    {
        return 'pemilihan';
    }

    protected function adminActivityName(): string
    {
        return (string) $this->name;
    }

    /**
     * Schedule changes made by the open, close, and extend actions are already recorded by those actions,
     * so only the fields that are edited in the form are listed here.
     *
     * @return list<string>
     */
    protected function adminActivityFields(): array
    {
        return ['name', 'show_results_early'];
    }
}
