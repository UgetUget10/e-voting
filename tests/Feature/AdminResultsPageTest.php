<?php

namespace Tests\Feature;

use App\Filament\Pages\Results;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\BallotReset;
use App\Services\CandidateTimeline;
use App\Services\ResultService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResultsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_shows_the_election_and_that_results_are_hidden_from_voters(): void
    {
        Election::create(['name' => 'Ketua OSIS 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Ketua OSIS 2026')
            ->assertSee('Hasil disembunyikan dari pemilih');
    }

    public function test_banner_says_results_are_visible_once_shown_early(): void
    {
        Election::create([
            'name' => 'Dini',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'show_results_early' => true,
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Hasil terlihat pemilih');
    }

    public function test_banner_asks_for_an_election_when_none_exists(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Belum ada pemilihan');
    }

    public function test_not_voted_count_ignores_admin_accounts_and_counts_only_pending_voters(): void
    {
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $admin = $this->superAdmin();
        $voted = User::factory()->create(['is_admin' => false]);
        User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voted->id]);
        Ballot::create(['user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(Results::class)
            ->assertViewHas('notVotedCount', 1);
    }

    public function test_tied_leaders_are_marked_as_a_tie(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);

        foreach ([$andi, $bunga] as $candidate) {
            $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        }

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Seri');
    }

    public function test_page_explains_when_there_are_no_fields(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Belum ada bidang');
    }

    public function test_live_updates_can_be_paused_and_resumed(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('wire:poll.visible.5s')
            ->callAction('toggleLive')
            ->assertSet('liveUpdates', false)
            ->assertSee('Live dijeda')
            ->assertDontSeeHtml('wire:poll.visible.5s')
            ->callAction('toggleLive')
            ->assertSet('liveUpdates', true)
            ->assertSeeHtml('wire:poll.visible.5s');
    }

    public function test_leader_shows_the_lead_over_second_place(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);

        foreach ([[$andi, 3], [$bunga, 1]] as [$candidate, $votes]) {
            for ($i = 0; $i < $votes; $i++) {
                $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
                Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
            }
        }

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Unggul 2 suara atas peringkat kedua');
    }

    public function test_lead_is_not_shown_for_a_tie(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);

        foreach ([$andi, $bunga] as $candidate) {
            $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        }

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertDontSee('Unggul');
    }

    public function test_closed_election_stops_live_polling_and_says_results_are_final(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertDontSeeHtml('wire:poll.visible.5s')
            ->assertSee('Voting ditutup, hasil final')
            ->assertActionHidden('toggleLive');
    }

    public function test_summary_text_lists_ranked_candidates_and_who_advances(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        Candidate::create(['name' => 'Bunga']);
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $andi->id]);

        $page = Livewire::actingAs($this->superAdmin())->test(Results::class)->assertActionExists('copySummary');

        $summary = (new \ReflectionMethod(Results::class, 'summaryText'))->invoke($page->instance());

        $this->assertStringContainsString("Presiden\n1. Andi - 1 suara (lolos)\n2. Bunga - 0 suara", $summary);
    }

    public function test_field_filter_narrows_the_page_to_one_field(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Bendahara', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertViewHas('results', fn ($results): bool => $results->count() === 2)
            ->set('fieldId', (string) $presiden->getKey())
            ->assertViewHas('results', fn ($results): bool => $results->count() === 1 && $results->first()['field']->is($presiden))
            ->assertViewHas('fieldOptions', fn (array $options): bool => count($options) === 2);
    }

    public function test_projector_mode_can_be_toggled(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertDontSeeHtml('admin-projector')
            ->callAction('toggleProjector')
            ->assertSet('projector', true)
            ->assertSeeHtml('admin-projector')
            ->assertSee('Keluar mode proyektor')
            ->callAction('toggleProjector')
            ->assertSet('projector', false);
    }

    public function test_page_offers_a_print_action(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertActionExists('print');
    }

    public function test_each_field_shows_how_votes_moved_over_time(): void
    {
        $field = Field::create(['name' => 'Presiden']);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->voteAt($field, $andi, now()->subHours(3));
        $this->voteAt($field, $bunga, now()->subHours(2));
        $this->voteAt($field, $andi, now()->subHour());

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('Pergerakan suara')
            ->assertSee('Andi')
            ->assertSee('Bunga');
    }

    public function test_timeline_counts_each_candidates_votes_in_the_field(): void
    {
        $field = Field::create(['name' => 'Presiden']);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->voteAt($field, $andi, now()->subHours(3));
        $this->voteAt($field, $bunga, now()->subHours(2));
        $this->voteAt($field, $andi, now()->subHour());

        $timeline = app(CandidateTimeline::class)->build($field, app(ResultService::class)->candidatesFor($field));

        $this->assertSame(['Andi', 'Bunga'], array_column($timeline['series'], 'name'));
        $this->assertSame([2, 1], array_column($timeline['series'], 'votes'));
    }

    public function test_timeline_is_hidden_for_a_field_without_votes(): void
    {
        Field::create(['name' => 'Bendahara']);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertDontSee('Pergerakan suara');
    }

    public function test_header_warns_about_fields_without_votes_and_fields_that_are_tied(): void
    {
        Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $empty = Field::create(['name' => 'Bendahara', 'position' => 1]);
        $tied = Field::create(['name' => 'Presiden', 'position' => 2]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->voteAt($tied, $andi, now()->subHour());
        $this->voteAt($tied, $bunga, now()->subMinutes(30));

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSee('1 bidang belum ada suara')
            ->assertSee('1 bidang seri');
    }

    public function test_projector_mode_marks_the_results_so_the_screen_can_rotate_fields(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Bendahara', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('data-projector="0"')
            ->callAction('toggleProjector')
            ->assertSeeHtml('data-projector="1"')
            ->assertSeeHtml('x-show="active === 0"');
    }

    public function test_standings_are_cached_and_counted_again_when_a_vote_is_added(): void
    {
        Cache::flush();

        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);

        $this->assertSame(0, $this->votesFor(app(ResultService::class), $field, $andi));
        $this->assertTrue(Cache::has(ResultService::CACHE_KEY));

        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $andi->id]);

        $this->assertSame(1, $this->votesFor(app(ResultService::class), $field, $andi));
    }

    public function test_renaming_a_candidate_clears_the_cached_standings(): void
    {
        Cache::flush();

        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        app(ResultService::class)->results();

        $andi->update(['name' => 'Andi Baru']);

        $this->assertSame('Andi Baru', app(ResultService::class)->results()->first()['candidates']->firstWhere('id', $andi->id)->name);
    }

    public function test_resetting_ballots_clears_the_cached_standings(): void
    {
        Cache::flush();

        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->voteAt($field, $andi, now());
        $this->assertSame(1, $this->votesFor(app(ResultService::class), $field, $andi));

        app(BallotReset::class)->reset();

        $this->assertSame(0, $this->votesFor(app(ResultService::class), $field, $andi));
    }

    public function test_field_in_the_address_bar_opens_the_page_on_that_field(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Bendahara', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->withQueryParams(['bidang' => (string) $presiden->getKey()])
            ->test(Results::class)
            ->assertSet('fieldId', (string) $presiden->getKey())
            ->assertViewHas('results', fn ($results): bool => $results->count() === 1);
    }

    public function test_page_offers_a_voter_preview_and_a_downloadable_card(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertActionExists('voterPreview')
            ->assertActionExists('downloadCard')
            ->assertActionExists('copySummary')
            ->assertSeeText('Lainnya');
    }

    public function test_card_data_lists_each_field_with_its_votes_and_who_advances(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->voteAt($field, $andi, now());
        $this->voteAt($field, $andi, now());
        $this->voteAt($field, $bunga, now());

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertViewHas('cardData', fn (array $card): bool => $card['fields'][0]['name'] === 'Presiden'
                && $card['fields'][0]['candidates'][0] === ['name' => 'Andi', 'votes' => 2, 'advances' => true]
                && $card['fields'][0]['candidates'][1] === ['name' => 'Bunga', 'votes' => 1, 'advances' => true]);
    }

    public function test_a_cut_off_line_separates_advancing_candidates_from_the_rest(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $cindy = Candidate::create(['name' => 'Cindy']);

        foreach ([[$andi, 3], [$bunga, 2], [$cindy, 1]] as [$candidate, $votes]) {
            for ($i = 0; $i < $votes; $i++) {
                $this->voteAt($field, $candidate, now());
            }
        }

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('admin-quota')
            ->assertSeeText('Batas lolos');
    }

    public function test_no_cut_off_line_when_every_candidate_advances(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->voteAt($field, $andi, now());
        $this->voteAt($field, $bunga, now());

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertDontSeeHtml('admin-quota');
    }

    public function test_rows_carry_the_rank_and_vote_data_the_page_animates_from(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->voteAt($field, $andi, now());

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('data-candidate-id="'.$andi->getKey().'"')
            ->assertSeeHtml('data-field-id="'.$field->getKey().'"')
            ->assertSeeHtml('data-rank="1"')
            ->assertSeeHtml('data-votes="1"')
            ->assertSeeHtml('data-rank-move');
    }

    public function test_trend_and_movement_charts_expose_their_points_for_hover(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->voteAt($field, $andi, now()->subMinutes(5));
        $this->voteAt($field, $andi, now());

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('data-chart="trend"')
            ->assertSeeHtml('data-chart="series"')
            ->assertSeeHtml('data-series-index="0"')
            ->assertSeeHtml('data-started-at=');
    }

    public function test_turnout_is_compared_with_the_last_archived_election(): void
    {
        Election::create([
            'name' => 'Pemilihan Lama',
            'opens_at' => now()->subMonth(),
            'closes_at' => now()->subWeek(),
            'archived_at' => now()->subWeek(),
            'results_snapshot' => ['turnout' => 10],
        ]);
        Election::create(['name' => 'Pemilihan Baru', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $voter = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voter->id]);
        Field::create(['name' => 'Presiden', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeText('100% partisipasi')
            ->assertSeeText('▲ 90 poin dari pemilihan sebelumnya');
    }

    public function test_projector_mode_shows_rotation_controls_and_keeps_the_motion_scripts_on_the_page(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(Results::class)
            ->assertSeeHtml('admin-rotation-bar')
            ->assertSeeHtml('x-ref="progress"')
            ->assertSeeHtml('window.adminMotion')
            ->assertSeeHtml('window.adminResultsMotion')
            ->assertSeeHtml('id="admin-results-card"');
    }

    private function votesFor(ResultService $results, Field $field, Candidate $candidate): int
    {
        return (int) $results->results()
            ->firstWhere('field.id', $field->getKey())['candidates']
            ->firstWhere('id', $candidate->getKey())
            ->votes_count;
    }

    private function voteAt(Field $field, Candidate $candidate, Carbon $castAt): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id])
            ->forceFill(['created_at' => $castAt])
            ->save();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
