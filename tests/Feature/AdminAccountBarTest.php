<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_panel_has_no_dark_mode_and_no_profile_dropdown(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse($panel->hasDarkMode());
        $this->assertFalse($panel->hasUserMenu());
    }

    public function test_the_admin_name_and_sign_out_button_sit_in_the_top_bar(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee('Super Admin')
            ->assertSee('Keluar dari panel admin?')
            ->assertSee(route('filament.admin.auth.logout'));
    }

    public function test_signing_out_ends_the_admin_session(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect();

        $this->assertGuest();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
