<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\Vote;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ElectionTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Field $osis;

    private Candidate $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->osis = Field::create(['name' => 'Ketua OSIS']);
        $this->a = Candidate::create(['name' => 'A']);

        Filament::setCurrentPanel(Filament::getPanel('pemilih'));
    }

    public function test_ballot_is_rejected_before_voting_opens(): void
    {
        Election::create(['name' => 'Belum buka', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->voteAs(User::factory()->create())
            ->assertNotified();

        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_ballot_is_rejected_after_voting_closes(): void
    {
        Election::create(['name' => 'Sudah tutup', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        $this->voteAs(User::factory()->create())
            ->assertNotified();

        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_voter_can_submit_a_ballot_while_voting_is_open(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->voteAs(User::factory()->create())
            ->assertHasNoErrors()
            ->assertRedirect('/pemilih/status-memilih');

        $this->assertDatabaseCount('ballots', 1);
        $this->assertDatabaseCount('votes', 2);
    }

    /**
     * Results are open to admins, who monitor them without a ballot and are not counted as voters.
     */
    private function resultsViewer(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_results_are_hidden_while_voting_is_open(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->actingAs($this->resultsViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('Hasil belum dibuka');
    }

    public function test_results_are_hidden_when_there_is_no_election(): void
    {
        $this->actingAs($this->resultsViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('Hasil belum dibuka');
    }

    public function test_results_are_visible_after_voting_closes(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subHour()]);

        $this->actingAs($this->resultsViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertDontSee('Hasil belum dibuka');
    }

    public function test_admin_can_open_results_early(): void
    {
        Election::create([
            'name' => 'Dini',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'show_results_early' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/pemilih/hasil')
            ->assertDontSee('Hasil belum dibuka');
    }

    public function test_only_admins_can_export_results(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('results.export'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('results.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_voter_who_already_voted_is_sent_to_their_status(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $voter = User::factory()->create();
        Ballot::create(['user_id' => $voter->id]);

        Livewire::actingAs($voter)
            ->test(Vote::class)
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_voter_must_pick_a_candidate_in_every_field(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        Field::create(['name' => 'Bendahara']);

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->set('data.picks', [$this->presiden->id => $this->a->id])
            ->call('submit')
            ->assertNotified()
            ->assertNoRedirect();

        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_candidate_used_in_two_other_fields_is_disabled_in_the_next_field(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $third = Field::create(['name' => 'Bendahara']);
        $fourth = Field::create(['name' => 'Sarpras']);

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->set('data.picks', [
                $this->presiden->id => $this->a->id,
                $this->osis->id => $this->a->id,
            ])
            ->assertSee('Sudah dipilih di 2 bidang lain');
    }

    private function voteAs(User $user)
    {
        return Livewire::actingAs($user)
            ->test(Vote::class)
            ->set('data.picks', [$this->presiden->id => $this->a->id, $this->osis->id => $this->a->id])
            ->call('submit');
    }

    public function test_overlapping_elections_are_found_and_open_ended_windows_overlap_everything(): void
    {
        $first = Election::create(['name' => 'Pertama', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $later = Election::create(['name' => 'Lanjutan', 'opens_at' => now()->addDays(3), 'closes_at' => now()->addDays(4)]);
        $openEnded = Election::create(['name' => 'Tanpa batas', 'opens_at' => null, 'closes_at' => null]);

        $this->assertSame(['Tanpa batas'], $later->overlappingElections()->pluck('name')->all());
        $this->assertSame(['Tanpa batas'], $first->overlappingElections()->pluck('name')->all());
        $this->assertSame(['Pertama', 'Lanjutan'], $openEnded->overlappingElections()->pluck('name')->all());
    }

    public function test_window_progress_is_null_without_both_dates_and_clamped_to_the_window(): void
    {
        $this->assertNull(Election::create(['name' => 'A', 'opens_at' => now()])->windowProgress());

        Carbon::setTestNow(now());
        $election = Election::create(['name' => 'B', 'opens_at' => now()->subHours(2), 'closes_at' => now()->addHours(2)]);

        $this->assertSame(50, $election->windowProgress());

        Carbon::setTestNow(now()->addHours(10));
        $this->assertSame(100, $election->windowProgress());

        Carbon::setTestNow(now()->subHours(20));
        $this->assertSame(0, $election->windowProgress());

        Carbon::setTestNow();
    }
}
