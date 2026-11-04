<?php

namespace App\Models;

use App\Models\Concerns\RecordsAdminActivity;
use App\Services\ResultService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Field extends Model
{
    use HasFactory, HasUuids, RecordsAdminActivity;

    protected $fillable = ['name', 'position'];

    /**
     * A field that has votes cannot be removed, since the votes would go with it.
     */
    protected static function booted(): void
    {
        static::deleting(function (Field $field): void {
            if ($field->hasVotes()) {
                throw new LogicException('Bidang yang sudah ada suara tidak dapat dihapus.');
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

    public function hasVotes(): bool
    {
        return $this->votes()->exists() || $this->corrections()->exists();
    }

    /**
     * The order voters go through fields in, and the order results are shown in.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    protected function adminActivityLabel(): string
    {
        return 'bidang';
    }

    protected function adminActivityName(): string
    {
        return (string) $this->name;
    }

    /**
     * Reordering fields only changes position, which is not worth an entry of its own.
     *
     * @return list<string>
     */
    protected function adminActivityFields(): array
    {
        return ['name'];
    }
}
