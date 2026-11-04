<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'voting:make-admin {email : Email of an existing user}';

    /**
     * @var string
     */
    protected $description = 'Grant admin access to the Filament panel for an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $this->info("{$user->email} can now access the admin panel.");

        return self::SUCCESS;
    }
}
