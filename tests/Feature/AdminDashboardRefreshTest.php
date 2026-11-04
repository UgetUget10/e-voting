<?php

namespace Tests\Feature;

use App\Filament\Pages\ElectionDashboard;
use App\Filament\Widgets\ActionNeeded;
use App\Filament\Widgets\AdminShortcuts;
use App\Filament\Widgets\ClosingChecklist;
use App\Filament\Widgets\ParticipationByDay;
use App\Filament\Widgets\VotingCountdown;
use App\Filament\Widgets\VotingStats;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_countdown_shows_how_far_the_voting_window_has_run(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subHours(6), 'closes_at' => now()->addHours(6)]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertSeeHtml('role="progressbar"')
            ->assertSeeHtml('aria-valuenow="50"');
    }

    public function test_countdown_has_no_progress_bar_without_a_closing_time(): void
    {
        Election::create(['name' => 'Tanpa batas', 'opens_at' => now()->subHour()]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingCountdown::class)
            ->assertDontSeeHtml('role="progressbar"');
    }

    public function test_attention_list_puts_urgent_items_first_and_counts_the_ones_to_act_on(): void
    {
        Election::create(['name' => 'Segera', 'opens_at' => now()->subDay(), 'closes_at' => now()->addHours(5)]);
        User::factory()->create(['is_admin' => false]);
        $this->problemBallot();

        $items = ActionNeeded::items();

        $this->assertSame('urgent', $items[0]['level']);
        $this->assertStringContainsString('ditutup kurang dari 24 jam', $items[0]['label']);
        $this->assertSame('warning', $items[1]['level']);
        $this->assertStringContainsString('suara bermasalah', $items[1]['label']);
        $this->assertSame(2, ActionNeeded::attentionCount($items));
    }

    public function test_attention_list_flags_ties_and_early_results(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(3), 'show_results_early' => true]);
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->vote($field, $andi);
        $this->vote($field, $bunga);

        $labels = array_column(ActionNeeded::items(), 'label');

        $this->assertContains('1 bidang seri. Kandidat yang seri ikut lolos ke tahap berikutnya.', $labels);
        $this->assertContains('Hasil sementara sudah terlihat pemilih sebelum voting ditutup.', $labels);
        $this->assertSame(1, ActionNeeded::attentionCount());
    }

    public function test_shortcuts_show_how_many_items_each_screen_holds(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => 'Andi']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(AdminShortcuts::class)
            ->assertSeeHtml('admin-shortcut-count')
            ->assertViewHas('shortcuts', fn (array $shortcuts): bool => collect($shortcuts)->firstWhere('label', 'Kandidat')['count'] === 2);
    }

    public function test_stats_compare_turnout_with_the_last_archived_election(): void
    {
        Election::create([
            'name' => 'Lama',
            'opens_at' => now()->subMonth(),
            'closes_at' => now()->subWeek(),
            'archived_at' => now()->subWeek(),
            'results_snapshot' => ['turnout' => 10],
        ]);
        Election::create(['name' => 'Baru', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(2)]);
        $voter = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voter->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSeeText('▲ 90 poin dari pemilihan sebelumnya')
            ->assertSeeHtml('data-count-to="1"');
    }

    public function test_estimate_says_when_voting_will_be_finished_at_the_current_pace(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(2)]);
        User::factory()->count(4)->create(['is_admin' => false]);
        $this->ballotAt(now()->subMinutes(2));
        $this->ballotAt(now()->subMinutes(4));

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSeeText('sekitar 30 menit')
            ->assertSeeText('Kecepatan 0,1 suara per menit, 4 pemilih lagi.');
    }

    public function test_estimate_says_it_cannot_predict_without_recent_votes(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(2)]);
        User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSeeText('Belum bisa')
            ->assertSeeText('Belum ada suara dalam 15 menit terakhir.');
    }

    public function test_estimate_warns_when_the_pace_will_not_finish_before_closing(): void
    {
        Election::create(['name' => 'Segera', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMinutes(10)]);
        User::factory()->count(100)->create(['is_admin' => false]);
        $this->ballotAt(now()->subMinutes(1));

        Livewire::actingAs($this->superAdmin())
            ->test(VotingStats::class)
            ->assertSeeText('Kecepatan saat ini tidak cukup sebelum penutupan.');
    }

    public function test_closing_checklist_is_only_shown_while_voting_is_open(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $this->assertTrue(ClosingChecklist::canView());

        Election::query()->update(['closes_at' => now()->subHour()]);
        $this->assertFalse(ClosingChecklist::canView());
    }

    public function test_closing_checklist_lists_what_still_needs_fixing(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        Field::create(['name' => 'Presiden', 'position' => 1]);
        User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ClosingChecklist::class)
            ->assertSeeText('Tidak ada suara bermasalah')
            ->assertSeeText('1 bidang belum ada suara')
            ->assertSeeText('1 pemilih belum memilih')
            ->assertSeeText('Tidak ada lonjakan suara hari ini');
    }

    public function test_participation_chart_highlights_today_as_the_strongest_bar(): void
    {
        $data = (new \ReflectionMethod(ParticipationByDay::class, 'getData'))
            ->invoke(Livewire::actingAs($this->superAdmin())->test(ParticipationByDay::class)->instance());

        $colors = $data['datasets'][0]['backgroundColor'];

        $this->assertCount(7, $colors);
        $this->assertSame('#c2410c', $colors[6]);
        $this->assertSame('#fdba74', $colors[0]);
    }

    public function test_dashboard_offers_a_daily_summary_download_and_keeps_the_attention_list_above_the_tabs(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionDashboard::class)
            ->assertActionExists('dailySummary')
            ->callAction('dailySummary')
            ->assertHasNoActionErrors();

        $this->get('/admin')
            ->assertOk()
            ->assertSeeText('Perlu tindakan');
    }

    public function test_dashboard_loads_the_tab_indicator_script(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ElectionDashboard::class)
            ->assertSeeHtml('adminTabIndicator');
    }

    private function problemBallot(): Ballot
    {
        return Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
    }

    private function ballotAt(Carbon $castAt): Ballot
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        $ballot->forceFill(['created_at' => $castAt])->save();

        return $ballot;
    }

    private function vote(Field $field, Candidate $candidate): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
