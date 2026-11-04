<?php

namespace Tests\Feature;

use App\Filament\Voter\Auth\VoterLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VoterReloginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pemilih'));
    }

    public function test_voter_can_sign_in_again_after_signing_out(): void
    {
        $voter = User::factory()->create([
            'username' => 'tes3',
            'password' => Hash::make('password'),
        ]);

        $this->signInThroughLoginPage($voter);
        $this->assertAuthenticatedAs($voter);

        $this->post(route('filament.pemilih.auth.logout'));
        $this->assertGuest();

        $this->signInThroughLoginPage($voter);
        $this->assertAuthenticatedAs($voter);
    }

    public function test_the_voter_panel_root_leads_to_the_ballot_without_redirecting_to_itself(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_voter_lands_on_the_status_page_after_signing_in(): void
    {
        $voter = User::factory()->create(['username' => 'tes3', 'password' => Hash::make('password')]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => $voter->username, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_voter_lands_on_the_status_page_even_after_opening_another_page_first(): void
    {
        $voter = User::factory()->create(['username' => 'tes3', 'password' => Hash::make('password')]);
        session()->put('url.intended', '/pemilih/hasil');

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => $voter->username, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/pemilih/status-memilih');
    }

    public function test_admin_lands_on_the_results_after_signing_in(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'is_admin' => true]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect('/pemilih/hasil');
    }

    private function signInThroughLoginPage(User $voter): void
    {
        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => $voter->username, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
    }
}
