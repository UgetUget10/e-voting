<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_regular_voter_cannot_open_the_panel(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        $this->actingAs($voter)->get('/admin')->assertForbidden();
    }

    public function test_guest_is_sent_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_results_page_is_visible_to_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/results')->assertOk();
    }

    public function test_admin_panel_loads_the_bundled_admin_theme(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('/build/assets/theme-', false);
    }
}
