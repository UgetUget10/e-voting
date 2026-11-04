<?php

namespace Tests\Feature;

use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterLoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_introduces_the_open_election(): void
    {
        Election::create(['name' => 'Pemilihan Ketua OSIS 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->get('/pemilih/login')
            ->assertOk()
            ->assertSee('Pemilihan Ketua OSIS 2026')
            ->assertSee('Voting sedang dibuka')
            ->assertSee('Masuk dengan akun pemilih Anda');
    }

    public function test_login_page_falls_back_to_a_generic_welcome_without_an_election(): void
    {
        $this->get('/pemilih/login')
            ->assertOk()
            ->assertSee('Selamat datang di E-Voting Matsanewa')
            ->assertSee('Voting belum dibuka');
    }
}
