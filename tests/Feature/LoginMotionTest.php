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

class LoginMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_voter_sees_the_welcome_once_after_signing_in(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pemilih'));

        User::factory()->create([
            'name' => 'Siswa Satu',
            'username' => 'siswa01',
            'password' => Hash::make('password'),
        ]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'siswa01', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/pemilih/status-memilih');

        $this->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertSee('Login berhasil')
            ->assertSee('Selamat datang, Siswa Satu')
            ->assertSee('Anda sudah masuk.');

        $this->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertDontSee('Login berhasil');
    }

    public function test_admin_sees_the_welcome_once_after_signing_in(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@sekolah.test',
            'is_admin' => true,
            'password' => Hash::make('password'),
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@sekolah.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Login berhasil')
            ->assertSee('Selamat datang, Admin Utama')
            ->assertSee('Panel pengelola siap digunakan.');

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('Login berhasil');
    }

    public function test_failed_voter_sign_in_does_not_play_the_welcome(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pemilih'));

        User::factory()->create(['username' => 'siswa01', 'password' => Hash::make('password')]);

        Livewire::test(VoterLogin::class)
            ->fillForm(['username' => 'siswa01', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        $this->get('/pemilih/login')
            ->assertOk()
            ->assertDontSee('Login berhasil');
    }
}
