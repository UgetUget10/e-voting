<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Controllers\VoterCardController;
use App\Models\AdminActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VoterCardsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    public function test_bulk_action_issues_new_passwords_for_voters_and_skips_admins(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['is_admin' => false, 'password' => 'old-password']);
        $oldHash = $voter->password;
        $adminHash = $admin->password;

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('issueCards', [$voter, $admin])
            ->assertRedirect(route('voter-cards.print'));

        $cards = session(VoterCardController::SESSION_KEY);

        $this->assertCount(1, $cards);
        $this->assertSame($voter->name, $cards[0]['name']);
        $this->assertNotSame($oldHash, $voter->fresh()->password);
        $this->assertTrue(Hash::check($cards[0]['password'], $voter->fresh()->password));
        $this->assertSame($adminHash, $admin->fresh()->password);
        $this->assertSame(1, AdminActivity::query()->where('description', 'like', 'Mencetak kartu pemilih%')->count());
    }

    public function test_cards_show_the_password_once(): void
    {
        $this->actingAs($this->superAdmin())
            ->withSession([VoterCardController::SESSION_KEY => [
                ['name' => 'Siti Aminah', 'email' => 'siti@example.test', 'password' => 'Abc123Xyz9'],
            ]])
            ->get(route('voter-cards.print'))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Abc123Xyz9');

        $this->get(route('voter-cards.print'))->assertNotFound();
    }

    public function test_voters_cannot_open_the_cards(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->withSession([VoterCardController::SESSION_KEY => []])
            ->get(route('voter-cards.print'))
            ->assertForbidden();
    }
}
