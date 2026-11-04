<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Vote;
use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterStatusGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
    }

    public function test_a_typed_ballot_address_goes_back_to_the_status_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/vote')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_a_typed_results_address_goes_back_to_the_status_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/hasil')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_a_wrong_token_does_not_open_the_ballot(): void
    {
        $this->actingAs(User::factory()->create());
        CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih'));

        $this->get('/pemilih/vote?akses=tidak-benar')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_the_status_page_opens_the_ballot_with_its_token(): void
    {
        $voter = User::factory()->create();

        $this->actingAs($voter)
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSeeHtml('href="'.Vote::getUrl(panel: 'pemilih').'?akses=');

        $this->actingAs($voter)
            ->get(CheckReceipt::gatedUrl(Vote::getUrl(panel: 'pemilih')))
            ->assertOk()
            ->assertSee('Pilih Kandidat');
    }

    public function test_admin_can_open_the_results_without_the_status_token(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/pemilih/hasil')
            ->assertOk();
    }

    public function test_the_status_page_itself_is_always_open_to_a_signed_in_voter(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk();
    }
}
