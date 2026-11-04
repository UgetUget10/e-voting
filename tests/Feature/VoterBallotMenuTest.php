<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use App\Filament\Voter\Pages\Vote;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoterBallotMenuTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Field $bendahara;

    private Candidate $andi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $this->bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $this->andi = Candidate::create(['name' => 'Andi', 'golongan' => 'III/a']);
    }

    private function openElection(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
    }

    public function test_confirmation_dialog_lists_the_pick_for_each_field(): void
    {
        $this->openElection();

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->set('data.picks', [$this->presiden->id => $this->andi->id])
            ->assertSee('Ringkasan pilihan')
            ->assertSee('Presiden')
            ->assertSee('Bendahara')
            ->assertSee('Belum dipilih');
    }

    public function test_candidate_card_shows_the_golongan(): void
    {
        $this->openElection();

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertSee('Golongan III/a')
            ->assertSee('Lihat profil');
    }

    public function test_countdown_is_shown_while_voting_is_open(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertSee('Ditutup dalam');
    }

    public function test_countdown_is_hidden_once_voting_has_closed(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertDontSee('Ditutup dalam');
    }

    public function test_field_chips_are_listed_in_step_order(): void
    {
        $this->openElection();

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertSeeInOrder(['voter-field-chip', 'Presiden', 'Bendahara']);
    }

    public function test_unsent_picks_are_restored_after_a_refresh(): void
    {
        $this->openElection();
        $voter = User::factory()->create();

        Livewire::actingAs($voter)
            ->test(Vote::class)
            ->set('data.picks.'.$this->presiden->id, $this->andi->id);

        Livewire::actingAs($voter)
            ->test(Vote::class)
            ->assertSet('data.picks', [$this->presiden->id => $this->andi->id]);
    }

    public function test_draft_drops_fields_and_candidates_that_no_longer_exist(): void
    {
        $this->openElection();
        session()->put(Vote::DRAFT_SESSION_KEY, [
            $this->presiden->id => $this->andi->id,
            9999 => $this->andi->id,
            $this->bendahara->id => 9999,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertSet('data.picks', [$this->presiden->id => $this->andi->id]);
    }

    public function test_draft_is_cleared_once_the_ballot_is_sent(): void
    {
        $this->openElection();
        $voter = User::factory()->create();

        Livewire::actingAs($voter)
            ->test(Vote::class)
            ->set('data.picks', [
                $this->presiden->id => $this->andi->id,
                $this->bendahara->id => $this->andi->id,
            ])
            ->call('submit')
            ->assertRedirect('/pemilih/status-memilih');

        $this->assertFalse(session()->has(Vote::DRAFT_SESSION_KEY));
    }

    public function test_failed_submit_tells_the_confirmation_dialog_to_close(): void
    {
        $this->openElection();

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->set('data.picks', [$this->presiden->id => $this->andi->id])
            ->call('submit')
            ->assertDispatched('vote-submit-failed')
            ->assertNotified();
    }

    public function test_profile_dialog_is_rendered_for_each_candidate(): void
    {
        $this->openElection();

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertSee('aria-label="Profil kandidat"', escape: false)
            ->assertSee('Tutup');
    }

    public function test_voter_who_has_voted_can_open_the_results_from_their_status(): void
    {
        $this->openElection();
        $voter = User::factory()->create();
        Ballot::create(['user_id' => $voter->id]);

        $this->actingAs($voter)
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Lihat Hasil')
            ->assertSeeHtml('href="'.Results::getUrl(panel: 'pemilih').'?akses=');

        $this->actingAs($voter)
            ->get($this->resultsUrl())
            ->assertOk();
    }

    public function test_status_offers_no_results_button_before_voting(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertDontSee('Lihat Hasil');
    }

    public function test_top_bar_shows_the_voter_name_and_a_logout_that_asks_first(): void
    {
        $this->openElection();
        $voter = User::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($voter)
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Keluar dari akun?')
            ->assertSeeHtml('action="'.route('filament.pemilih.auth.logout').'"');
    }

    public function test_logging_out_from_the_panel_signs_the_voter_out(): void
    {
        $voter = User::factory()->create();

        $this->actingAs($voter)
            ->post(route('filament.pemilih.auth.logout'))
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_the_voter_panel_has_no_dark_mode(): void
    {
        $this->assertFalse(Filament::getPanel('pemilih')->hasDarkMode());
    }

    public function test_voter_who_has_not_voted_is_blocked_from_the_results_even_after_voting_closes(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create())
            ->get($this->resultsUrl())
            ->assertRedirectContains('/pemilih/vote');
    }

    public function test_admin_can_open_the_results_without_voting(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get($this->resultsUrl())
            ->assertOk()
            ->assertDontSee('Hasil voting hanya bisa dilihat');
    }

    public function test_voter_who_has_not_voted_gets_the_alert_on_the_ballot_page(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get($this->resultsUrl())
            ->assertOk()
            ->assertSee('Hasil voting hanya bisa dilihat setelah Anda memberikan suara')
            ->assertSee('Saya mengerti');
    }

    public function test_status_page_uses_the_new_title_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Status Memilih')
            ->assertDontSee('Cek tanda terima');
    }

    public function test_ballot_says_voting_has_not_opened_yet_with_the_opening_time(): void
    {
        Election::create(['name' => 'Belum', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertSee('Voting belum dibuka')
            ->assertSee('Surat suara tersedia pada')
            ->assertDontSee('Voting ditutup');
    }

    public function test_ballot_hides_the_candidates_until_voting_opens(): void
    {
        Election::create(['name' => 'Belum', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertSee('Dibuka dalam')
            ->assertSee('wire:poll.visible.5s', escape: false)
            ->assertDontSee('Bidang 1 dari')
            ->assertDontSee('Kirim suara');
    }

    public function test_ballot_shows_the_candidates_once_voting_has_opened(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertSee('Bidang 1 dari')
            ->assertDontSee('Dibuka dalam');
    }

    public function test_ballot_says_voting_has_closed_once_it_is_over(): void
    {
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subHour()]);

        $this->actingAs(User::factory()->create())
            ->get($this->ballotUrl())
            ->assertOk()
            ->assertSee('Voting sudah ditutup')
            ->assertDontSee('belum dibuka');
    }

    public function test_results_redirect_explains_voting_has_not_opened_yet(): void
    {
        Election::create(['name' => 'Belum', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get($this->resultsUrl())
            ->assertOk()
            ->assertSee('Voting belum dibuka. Hasil voting bisa dilihat')
            ->assertDontSee('Silakan pilih kandidat terlebih dahulu');
    }

    public function test_status_before_voting_opens_offers_no_vote_button(): void
    {
        Election::create(['name' => 'Belum', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Voting belum dibuka. Anda bisa memilih saat voting dibuka.')
            ->assertDontSee('Pilih Sekarang');
    }

    public function test_status_page_shows_the_election_banner_with_the_closing_countdown(): void
    {
        $this->openElection();

        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Voting dibuka')
            ->assertSee('Berjalan')
            ->assertSee('Ditutup dalam')
            ->assertSee('Ditutup ');
    }

    public function test_status_page_shows_when_voting_opens_before_it_starts(): void
    {
        Election::create(['name' => 'Belum', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Belum')
            ->assertSee('Dibuka ')
            ->assertDontSee('Ditutup dalam');
    }

    /**
     * The ballot as the status page links to it, with the token that opens it.
     */
    private function ballotUrl(): string
    {
        return CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih'));
    }

    /**
     * The results as the status page links to them, with the token that opens them.
     */
    private function resultsUrl(): string
    {
        return CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih'));
    }
}
