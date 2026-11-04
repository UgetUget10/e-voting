<?php

namespace Tests\Feature;

use App\Filament\Widgets\ActionNeeded;
use App\Filament\Widgets\AdminShortcuts;
use App\Filament\Widgets\ElectionReadiness;
use App\Filament\Widgets\ElectionTimeline;
use App\Filament\Widgets\ParticipationByDay;
use App\Filament\Widgets\VotingCountdown;
use App\Filament\Widgets\VotingSpeed;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_countdown_counts_down_to_closing_while_voting_is_open(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertSee('Voting ditutup dalam')
            ->assertSeeHtml('x-text="parts"');
    }

    public function test_countdown_counts_down_to_opening_before_voting_starts(): void
    {
        Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDays(3), 'closes_at' => now()->addDays(4)]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertSee('Voting dibuka dalam');
    }

    public function test_countdown_states_when_voting_has_closed(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertSee('Voting sudah ditutup')
            ->assertDontSeeHtml('x-text="parts"');
    }

    public function test_countdown_asks_for_an_election_when_none_exists(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertSee('Belum ada pemilihan')
            ->assertSee('Buka menu Pemilihan');
    }

    public function test_shortcuts_link_to_the_screens_admins_use_most(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(AdminShortcuts::class)
            ->assertSee('Hasil voting')
            ->assertSee('Suara masuk')
            ->assertSee('Kandidat')
            ->assertSee('Pemilih');
    }

    public function test_readiness_flags_what_is_missing_before_voting_opens(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ElectionReadiness::class)
            ->assertSee('Belum ada pemilihan')
            ->assertSee('Belum ada bidang')
            ->assertSee('Belum ada kandidat');
    }

    public function test_readiness_counts_candidates_without_photo(): void
    {
        Election::create(['name' => 'Siap', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionReadiness::class)
            ->assertSee('1 kandidat belum ada foto')
            ->assertSee('Jadwal buka dan tutup sudah diatur');
    }

    public function test_readiness_says_everything_is_ready_when_all_checks_pass(): void
    {
        Election::create(['name' => 'Siap', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionReadiness::class)
            ->assertSee('Semua siap untuk dibuka.');
    }

    public function test_participation_chart_counts_ballots_per_jakarta_day(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);
        $yesterday = Ballot::create(['user_id' => $voter->id]);
        $yesterday->forceFill(['created_at' => now('Asia/Jakarta')->subDay()->utc()])->save();
        Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        $widget = Livewire::actingAs($this->superAdmin())->test(ParticipationByDay::class)->assertSee('Partisipasi 7 hari terakhir');

        $data = (new ReflectionMethod(ParticipationByDay::class, 'getData'))->invoke($widget->instance());

        $this->assertCount(7, $data['labels']);
        $this->assertSame([0, 0, 0, 0, 0, 1, 1], $data['datasets'][0]['data']);
    }

    public function test_dashboard_page_shows_the_new_widgets(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(VotingCountdown::class)
            ->assertSeeLivewire(AdminShortcuts::class)
            ->assertSeeLivewire(ElectionReadiness::class)
            ->assertSeeLivewire(ParticipationByDay::class)
            ->assertSeeLivewire(ActionNeeded::class);
    }

    public function test_action_needed_flags_pending_voters_when_voting_closes_within_a_day(): void
    {
        Election::create(['name' => 'Segera', 'opens_at' => now()->subDay(), 'closes_at' => now()->addHours(5)]);
        User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ActionNeeded::class)
            ->assertSee('Voting ditutup kurang dari 24 jam lagi. 1 pemilih belum memilih.');
    }

    public function test_action_needed_says_nothing_is_pending_when_everyone_has_voted(): void
    {
        Election::create(['name' => 'Aman', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(5)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ActionNeeded::class)
            ->assertSee('Tidak ada yang perlu tindakan saat ini');
    }

    public function test_action_needed_asks_for_an_election_when_none_exists(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ActionNeeded::class)
            ->assertSee('Belum ada pemilihan');
    }

    public function test_voting_speed_counts_ballots_per_minute_over_the_last_fifteen_minutes(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);
        $ballot = Ballot::create(['user_id' => $voter->id]);
        $ballot->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $data = (new ReflectionMethod(VotingSpeed::class, 'getData'))->invoke(Livewire::actingAs($this->superAdmin())->test(VotingSpeed::class)->instance());

        $this->assertCount(15, $data['labels']);
        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
    }

    public function test_election_timeline_places_each_dated_election_on_one_axis(): void
    {
        Election::create(['name' => 'Pertama', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        Election::create(['name' => 'Kedua', 'opens_at' => now()->subHours(2), 'closes_at' => now()->addHours(2)]);
        Election::create(['name' => 'Tanpa jadwal']);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionTimeline::class)
            ->assertSee('Pertama')
            ->assertSee('Kedua')
            ->assertSee('1 pemilihan tanpa jadwal lengkap tidak ditampilkan.');
    }

    public function test_election_timeline_explains_when_nothing_is_dated(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ElectionTimeline::class)
            ->assertSee('Belum ada pemilihan dengan jadwal buka dan tutup.');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
