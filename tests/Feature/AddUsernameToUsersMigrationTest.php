<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddUsernameToUsersMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_04_131348_add_username_to_users_table.php';

    public function test_existing_voters_get_a_username_from_their_email_and_admins_do_not(): void
    {
        $older = User::factory()->create(['email' => 'ahmad.fauzi@sekolah.test', 'username' => null, 'created_at' => now()->subDays(2)]);
        $newer = User::factory()->create(['email' => 'ahmad.fauzi@gmail.test', 'username' => null, 'created_at' => now()->subDay()]);
        $punctuated = User::factory()->create(['email' => 'Siti+kelas7@sekolah.test', 'username' => null]);
        $short = User::factory()->create(['email' => 'ab@sekolah.test', 'username' => null]);
        $admin = User::factory()->create(['email' => 'admin@sekolah.test', 'username' => null, 'is_admin' => true]);

        $migration = require base_path(self::MIGRATION);
        $migration->down();
        $migration->up();

        $this->assertSame('ahmad.fauzi', $older->fresh()->username);
        $this->assertSame('ahmad.fauzi2', $newer->fresh()->username);
        $this->assertSame('siti.kelas7', $punctuated->fresh()->username);
        $this->assertSame('pemilihab', $short->fresh()->username);
        $this->assertNull($admin->fresh()->username);
    }
}
