<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use App\Filament\Voter\Pages\Vote;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoterVoteLockTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Candidate $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create(['name' => 'Test', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->andi = Candidate::create(['name' => 'Andi']);
    }

    public function test_voter_who_has_voted_is_sent_from_the_ballot_to_their_status(): void
    {
        $voter = User::factory()->create();
        app(BallotService::class)->submit($voter, [$this->presiden->id => $this->andi->id]);

        $this->actingAs($voter)
            ->get(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')))
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_ballot_and_results_are_kept_out_of_the_navigation(): void
    {
        $this->assertFalse(Vote::shouldRegisterNavigation());
        $this->assertFalse(Results::shouldRegisterNavigation());
        $this->assertFalse(CheckReceipt::shouldRegisterNavigation());
    }

    public function test_voter_who_has_voted_is_told_their_vote_is_recorded_on_the_status_page(): void
    {
        $voter = User::factory()->create();
        app(BallotService::class)->submit($voter, [$this->presiden->id => $this->andi->id]);

        $this->actingAs($voter)
            ->followingRedirects()
            ->get(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertSee('Terima kasih, Anda sudah memilih.')
            ->assertDontSeeHtml('<dialog x-ref="voterWarning" class="voter-dialog"');
    }

    public function test_admin_sees_no_voter_warning_on_the_results_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertDontSee('Hasil voting hanya bisa dilihat');
    }

    public function test_voter_who_has_not_voted_is_sent_from_the_results_to_the_ballot_with_an_alert(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))
            ->assertRedirectContains('/pemilih/vote');

        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get(CheckReceipt::gatedUrl(Results::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertSeeHtml('<dialog x-ref="notVotedWarning" class="voter-dialog"')
            ->assertSee('Hasil voting hanya bisa dilihat setelah Anda memberikan suara');
    }

    public function test_a_second_submit_from_an_old_tab_is_rejected_on_the_server(): void
    {
        $voter = User::factory()->create();
        app(BallotService::class)->submit($voter, [$this->presiden->id => $this->andi->id]);

        $this->expectException(ValidationException::class);

        app(BallotService::class)->submit($voter, [$this->presiden->id => $this->andi->id]);
    }
}
