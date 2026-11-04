<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Models\AdminActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminActivityLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_the_full_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $entry = AdminActivity::create(['user_id' => $admin->id, 'description' => 'Menutup pemilihan.']);

        Livewire::actingAs($admin)
            ->test(ListAdminActivities::class)
            ->assertCanSeeTableRecords([$entry]);
    }

    public function test_other_admins_cannot_open_the_log(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/admin-activities')
            ->assertForbidden();
    }
}
