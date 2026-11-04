<?php

namespace Tests\Feature;

use App\Filament\Widgets\LatestBallots;
use App\Filament\Widgets\VotingStats;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_voting_status_describes_when_the_election_closes(): void
    {
        Election::create([
            'name' => 'Berjalan',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSee('Dibuka')
            ->assertSee('Ditutup');
    }

    public function test_voting_status_shows_the_closing_date_in_indonesian_once_closed(): void
    {
        Election::create([
            'name' => 'Selesai',
            'opens_at' => Carbon::create(2026, 10, 1, 8, 0, 0, 'Asia/Jakarta')->utc(),
            'closes_at' => Carbon::create(2026, 10, 5, 15, 30, 0, 'Asia/Jakarta')->utc(),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSee('Ditutup')
            ->assertSee('5 Okt 2026, 15:30');
    }

    public function test_voting_status_asks_for_an_election_when_none_exists(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSee('Buat pemilihan di menu Pemilihan');
    }

    public function test_latest_ballots_widget_lists_the_five_most_recent_voters(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $candidate = Candidate::create(['name' => 'Andi']);

        foreach (range(1, 6) as $index) {
            $voter = User::factory()->create(['name' => "Pemilih {$index}"]);
            $ballot = Ballot::create(['user_id' => $voter->id]);
            $ballot->forceFill(['created_at' => now()->subMinutes(10 - $index)])->save();
            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        }

        Livewire::actingAs($this->superAdmin())
            ->test(LatestBallots::class)
            ->assertSee(Ballot::query()->latest('created_at')->value('receipt_code'))
            ->assertSee('Pemilih 6')
            ->assertDontSee('Pemilih 1');
    }

    public function test_dashboard_registers_the_latest_ballots_widget(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk();

        $this->assertContains(LatestBallots::class, Filament::getPanel('admin')->getWidgets());
    }

    public function test_dashboard_uses_the_indonesian_title(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan Pemilihan');
    }

    public function test_every_admin_menu_carries_the_ballot_ornament(): void
    {
        $admin = $this->superAdmin();

        foreach (['/admin', '/admin/results', '/admin/ballots', '/admin/candidates', '/admin/elections', '/admin/fields', '/admin/users'] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('admin-page-ornament', escape: false);
        }
    }

    public function test_latest_ballots_widget_hides_voter_names_from_admins_who_are_not_super_admins(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $candidate = Candidate::create(['name' => 'Andi']);
        $voter = User::factory()->create(['name' => 'Pemilih Rahasia', 'email' => 'rahasia@example.com']);
        $ballot = Ballot::create(['user_id' => $voter->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(LatestBallots::class)
            ->assertSee($ballot->receipt_code)
            ->assertDontSee('Pemilih Rahasia')
            ->assertDontSee('rahasia@example.com');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
