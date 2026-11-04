<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'username', 'password', 'is_admin', 'suspended_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, InteractsWithEmailAuthentication, Notifiable;

    /**
     * Voters type this to sign in: 3 to 30 letters, digits, dots, underscores or dashes, starting with a letter or digit.
     */
    public const USERNAME_PATTERN = '/^[a-z0-9][a-z0-9._-]{2,29}$/i';

    /**
     * A username for a voter imported without one: the part of the email before the @, reduced to the allowed
     * characters. A number is added when that name is taken, so the result is always free.
     *
     * @param  array<string, mixed>  $taken  Usernames already in use, as keys.
     */
    public static function usernameFromEmail(string $email, array $taken): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '.')
            ->trim('._-')
            ->limit(26, '')
            ->toString();

        if (strlen($base) < 3) {
            $base = 'pemilih'.$base;
        }

        $username = $base;
        $suffix = 2;

        while (isset($taken[$username])) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Deleting a voter would cascade to their ballot, so a voter who has voted cannot be removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if ($user->ballot()->exists()) {
                throw new LogicException('Pengguna yang sudah memilih tidak dapat dihapus.');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * A suspended account keeps its ballot and history, but cannot sign in to either panel until it is reactivated.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->is_admin,
            'pemilih' => true,
            default => false,
        };
    }

    /**
     * Super admins may see voter identities next to their ballots. Other admins see ballots by receipt code only.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    public function ballot(): HasOne
    {
        return $this->hasOne(Ballot::class);
    }
}
