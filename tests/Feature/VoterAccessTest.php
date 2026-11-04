<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use App\Filament\Voter\Pages\Vote;
use App\Models\Ballot;
use App\Models\Field;
use App\Models\User;
use App\Services\ParticipationTrend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_voter_panel(): void
    {
        $this->get('/')->assertRedirect('/pemilih');
    }

    public function test_legacy_vote_and_results_urls_redirect_into_the_panel(): void
    {
        $this->get('/vote')->assertRedirect('/pemilih/vote');
        $this->get('/results')->assertRedirect('/pemilih/hasil');
    }

    public function test_guest_must_login_to_the_voter_panel(): void
    {
        $this->get(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')))->assertRedirect('/pemilih/login');
        $this->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))->assertRedirect('/pemilih/login');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_voter_can_open_the_ballot_in_the_panel(): void
    {
        Field::create(['name' => 'Presiden']);

        $this->actingAs(User::factory()->create())
            ->get(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertSee('Pilih Kandidat');
    }

    public function test_admin_is_redirected_away_from_the_ballot_page(): void
    {
        Field::create(['name' => 'Presiden']);

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(Vote::class)
            ->assertRedirect(Results::getUrl(panel: 'pemilih'));
    }

    public function test_admin_sees_a_monitoring_note_instead_of_a_vote_prompt_on_results(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertSee('Akun admin memantau hasil')
            ->assertDontSee('Pilih sekarang');
    }

    public function test_participation_never_exceeds_the_registered_voters(): void
    {
        Ballot::create(['user_id' => User::factory()->create(['is_admin' => true])->id]);

        $trend = app(ParticipationTrend::class)->build(0);

        $this->assertSame(100, $trend['percent']);
        $this->assertStringNotContainsString('-', $trend['line']);
    }

    public function test_voter_who_already_voted_can_open_the_results(): void
    {
        $voter = User::factory()->create();
        Ballot::create(['user_id' => $voter->id]);

        $this->actingAs($voter)
            ->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))
            ->assertOk();
    }

    public function test_logout_returns_to_the_voter_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/pemilih/login');

        $this->assertGuest();
    }

    public function test_regular_voter_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')
            ->assertForbidden();
    }
}
