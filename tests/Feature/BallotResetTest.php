<?php

namespace Tests\Feature;

use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BallotResetTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Candidate $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $this->presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $this->andi = Candidate::create(['name' => 'Andi']);

        foreach (range(1, 3) as $index) {
            $voter = User::factory()->create(['is_admin' => false]);
            $ballot = Ballot::create(['user_id' => $voter->id]);
            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $this->presiden->id, 'candidate_id' => $this->andi->id]);
        }
    }

    public function test_super_admin_sees_the_reset_action(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertActionVisible('resetBallots');
    }

    public function test_other_admins_cannot_see_the_reset_action(): void
    {
        $admin = $this->ballotViewerAdmin();

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->assertActionHidden('resetBallots');
    }

    public function test_reset_removes_all_ballots_and_picks_but_keeps_voters_and_setup(): void
    {
        $admin = $this->superAdmin();
        $userCount = User::count();

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->callAction('resetBallots', [
                'confirmation' => 'RESET',
                'password' => 'password',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('ballots', 0);
        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('fields', 1);
        $this->assertDatabaseCount('candidates', 1);
        $this->assertDatabaseCount('elections', 1);
    }

    public function test_reset_needs_the_typed_confirmation(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('resetBallots', [
                'confirmation' => 'reset',
                'password' => 'password',
            ])
            ->assertHasActionErrors(['confirmation']);

        $this->assertDatabaseCount('ballots', 3);
    }

    public function test_reset_needs_the_current_password(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('resetBallots', [
                'confirmation' => 'RESET',
                'password' => 'salah-banget',
            ])
            ->assertHasActionErrors(['password']);

        $this->assertDatabaseCount('ballots', 3);
    }

    public function test_reset_cannot_be_run_by_a_non_super_admin(): void
    {
        Livewire::actingAs($this->ballotViewerAdmin())
            ->test(ListBallots::class)
            ->assertActionHidden('resetBallots');

        $this->assertDatabaseCount('ballots', 3);
    }

    public function test_reset_leaves_voters_without_a_ballot(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('resetBallots', [
                'confirmation' => 'RESET',
                'password' => 'password',
            ]);

        $voter = User::where('is_admin', false)->firstOrFail();
        $this->assertFalse($voter->ballot()->exists());
    }

    private function ballotViewerAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'panitia', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Ballot', 'guard_name' => 'web']));

        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
