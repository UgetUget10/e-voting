<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_pages_carry_the_leaving_farewell_for_the_sign_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('logout-farewell', escape: false)
            ->assertSee('data-state="idle"', escape: false)
            ->assertSee('Sedang keluar');
    }

    public function test_admin_sign_out_goes_to_the_login_page_without_a_second_farewell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect('/admin/login');

        $this->assertGuest();

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('Anda telah keluar');
    }

    public function test_voter_sign_out_goes_to_the_login_page_without_a_second_farewell(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        $this->actingAs($voter)
            ->post(route('filament.pemilih.auth.logout'))
            ->assertRedirect('/pemilih/login');

        $this->assertGuest();

        $this->get('/pemilih/login')
            ->assertOk()
            ->assertDontSee('Anda telah keluar');
    }

    public function test_sign_out_from_the_profile_menu_ends_the_session(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        $this->actingAs($voter)
            ->post('/logout')
            ->assertRedirect('/pemilih/login');

        $this->assertGuest();
    }
}
