<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MAX_USERNAME_LENGTH = 30;

    /**
     * Voters sign in with a username. Admins keep signing in with their email, so only voters get one. Voters who
     * already exist get a username built from the front of their email, so they can keep their current password.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });

        $taken = [];

        DB::table('users')
            ->where('is_admin', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(function (object $voter) use (&$taken): void {
                $username = $this->usernameFromEmail($voter->email, $taken);
                $taken[$username] = true;

                DB::table('users')->where('id', $voter->id)->update(['username' => $username]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Lowercase letters, digits, dots, underscores and dashes from the part before the @. A number is added when
     * the name is already taken, and short names get a prefix so they meet the minimum length.
     *
     * @param  array<string, true>  $taken
     */
    private function usernameFromEmail(string $email, array $taken): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '.')
            ->trim('._-')
            ->limit(self::MAX_USERNAME_LENGTH - 4, '')
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
};
