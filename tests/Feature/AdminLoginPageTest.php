<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_shows_the_same_intro_as_the_voter_login(): void
    {
        Election::create(['name' => 'Pemilihan Ketua OSIS 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Panel Pengelola')
            ->assertSee('Masuk untuk mengelola pemilihan')
            ->assertSee('Voting sedang dibuka')
            ->assertDontSee('Masuk dengan akun pemilih Anda');
    }

    public function test_admin_pages_use_the_full_content_width(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('fi-width-full', escape: false);
    }
}
