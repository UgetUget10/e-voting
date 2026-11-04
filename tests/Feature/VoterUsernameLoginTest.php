<?php

namespace Tests\Feature;

use App\Filament\Voter\Auth\VoterLogin;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VoterUsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pemilih'));
    }

    public function test_voter_signs_in_with_the_username(): void
    {
        $voter = User::factory()->create(['username' => 'siswa01', 'password' => Hash::make('password')]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'siswa01', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/pemilih/status-memilih');

        $this->assertAuthenticatedAs($voter);
    }

    public function test_username_is_not_case_sensitive(): void
    {
        $voter = User::factory()->create(['username' => 'siswa01', 'password' => Hash::make('password')]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => '  SISWA01 ', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($voter);
    }

    public function test_voter_cannot_sign_in_with_the_email_on_file(): void
    {
        User::factory()->create([
            'email' => 'siswa@sekolah.test',
            'username' => 'siswa01',
            'password' => Hash::make('password'),
        ]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'siswa@sekolah.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        $this->assertGuest();
    }

    public function test_a_wrong_password_shows_the_error_under_the_username_field(): void
    {
        User::factory()->create(['username' => 'siswa01', 'password' => Hash::make('password')]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'siswa01', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        $this->assertGuest();
    }

    public function test_admin_still_signs_in_with_the_email_on_the_voter_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@sekolah.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'admin@sekolah.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/pemilih/hasil');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_panel_still_signs_in_with_the_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create([
            'email' => 'admin@sekolah.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@sekolah.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }
}
