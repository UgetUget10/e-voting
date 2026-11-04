<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

class Ballot extends Model
{
    use HasFactory, HasUuids;

    /**
     * Ballots sent within this many minutes are marked as new in the admin list.
     */
    public const RECENT_MINUTES = 5;

    /**
     * How long the admin list reuses its counts. They scan every ballot, so the page and its polling share one
     * result per interval instead of recounting for each request.
     */
    public const LIVE_CACHE_SECONDS = 5;

    /**
     * Summaries keyed by the fingerprint below stay correct until the data changes, so they can be kept longer.
     */
    public const KEYED_CACHE_SECONDS = 120;

    protected $fillable = ['user_id', 'receipt_code', 'needs_review'];

    /**
     * Submitted ballots are final: no code path may remove one. Each ballot gets a random receipt code
     * that identifies it without naming the voter.
     */
    protected static function booted(): void
    {
        static::creating(function (Ballot $ballot): void {
            $ballot->receipt_code ??= static::newReceiptCode();
        });

        static::deleting(function (): void {
            throw new LogicException('Suara yang sudah dikirim tidak dapat dihapus.');
        });
    }

    /**
     * The counts the admin list shows in its tabs and overview cards. They are shared by every refresh
     * within the interval, so one request scans the ballots once.
     *
     * @return array{all: int, recent: int, today: int, problems: int, review: int}
     */
    /**
     * A small summary of everything the ballot screens show: how many ballots there are, the newest id, when one
     * last changed, and the same for internal notes. Two equal fingerprints mean nothing visible has changed.
     */
    public static function liveFingerprint(): string
    {
        $ballots = static::query()->selectRaw('count(*) as total, max(id) as newest, max(updated_at) as changed')->first();
        $notes = BallotNote::query()->selectRaw('count(*) as total, max(id) as newest')->first();

        return implode('|', [$ballots->total, $ballots->newest, $ballots->changed, $notes->total, $notes->newest]);
    }

    public static function liveCounts(): array
    {
        return Cache::remember('ballots.tab-counts.'.static::liveFingerprint().'.'.now()->format('YmdHi'), self::KEYED_CACHE_SECONDS, fn (): array => [
            'all' => static::query()->count(),
            'recent' => static::query()->recent()->count(),
            'today' => static::query()->sentToday()->count(),
            'problems' => static::query()->withProblems()->count(),
            'review' => static::query()->needsReview()->count(),
        ]);
    }

    public static function newReceiptCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (static::query()->where('receipt_code', $code)->exists());

        return $code;
    }

    /**
     * The earliest send time that still counts as new.
     */
    public static function recentSince(): CarbonInterface
    {
        return now()->subMinutes(self::RECENT_MINUTES);
    }

    public function scopeRecent(Builder $query): void
    {
        $query->where('created_at', '>=', self::recentSince());
    }

    public function scopeSentToday(Builder $query): void
    {
        $startOfDay = now('Asia/Jakarta')->startOfDay();

        $query->where('created_at', '>=', $startOfDay->copy()->utc())
            ->where('created_at', '<', $startOfDay->copy()->addDay()->utc());
    }

    public function scopeWithoutPicks(Builder $query): void
    {
        $query->doesntHave('votes');
    }

    /**
     * Ballots with no voter attached. The database's foreign key makes this impossible in practice, so the
     * live counts check this one only. The full check of picks runs from "Verifikasi integritas".
     */
    public function scopeWithoutVoter(Builder $query): void
    {
        $query->doesntHave('user');
    }

    /**
     * Ballots that would make the results questionable: no picks, or no voter.
     */
    public function scopeWithProblems(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->withoutPicks()
            ->orWhere(fn (Builder $query) => $query->withoutVoter()));
    }

    public function scopeNeedsReview(Builder $query): void
    {
        $query->where('needs_review', true);
    }

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Admin actions taken on this ballot, oldest first when read in order. Only entries recorded with it as the subject appear here.
     */
    public function adminActivities(): MorphMany
    {
        return $this->morphMany(AdminActivity::class, 'subject');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(BallotNote::class)->latest('id');
    }
}
