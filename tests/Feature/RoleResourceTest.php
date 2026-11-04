<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Widgets\RoleStats;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_page_renders_for_admins(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/shield/roles')
            ->assertOk()
            ->assertSee('Pengguna');
    }

    public function test_role_list_has_a_create_button_in_the_header(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListRoles::class)
            ->assertActionExists(CreateAction::class);
    }

    public function test_stats_count_roles_and_how_many_are_in_use(): void
    {
        $panitia = $this->role('panitia');
        $this->role('pengawas');
        $user = User::factory()->create(['is_admin' => true]);
        $user->assignRole($panitia);

        Livewire::actingAs($this->superAdmin())
            ->test(RoleStats::class)
            ->assertSee('Jumlah peran')
            ->assertSee('Peran dipakai')
            ->assertSee('1 peran belum punya pengguna');
    }

    public function test_super_admin_role_cannot_be_deleted_from_the_list(): void
    {
        $superAdmin = $this->superAdmin()->roles->first();

        Livewire::actingAs($this->superAdmin())
            ->test(ListRoles::class)
            ->assertTableActionHidden(DeleteAction::class, $superAdmin)
            ->callTableBulkAction(DeleteBulkAction::class, [$superAdmin]);

        $this->assertDatabaseHas('roles', ['id' => $superAdmin->id]);
    }

    public function test_super_admin_role_cannot_be_deleted_from_its_edit_page(): void
    {
        $superAdmin = $this->superAdmin()->roles->first();

        Livewire::actingAs($this->superAdmin())
            ->test(EditRole::class, ['record' => $superAdmin->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_super_admin_role_name_is_locked_in_the_form(): void
    {
        $superAdmin = $this->superAdmin()->roles->first();

        Livewire::actingAs($this->superAdmin())
            ->test(EditRole::class, ['record' => $superAdmin->getKey()])
            ->assertFormFieldIsDisabled('name');
    }

    public function test_other_roles_can_be_deleted(): void
    {
        $role = $this->role('pengawas');

        Livewire::actingAs($this->superAdmin())
            ->test(ListRoles::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$role]);

        $this->assertModelMissing($role);
    }

    public function test_role_can_be_created_from_the_list_page(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateRole::class)
            ->fillForm(['name' => 'pengawas', 'guard_name' => 'web'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('roles', ['name' => 'pengawas']);
    }

    public function test_duplicate_copies_permissions_into_a_new_unused_role(): void
    {
        $source = $this->role('panitia');
        $permission = Permission::firstOrCreate(['name' => 'ViewAny:Ballot', 'guard_name' => 'web']);
        $source->givePermissionTo($permission);

        Livewire::actingAs($this->superAdmin())
            ->test(ListRoles::class)
            ->callTableAction('duplicate', $source);

        $copy = Role::where('name', 'panitia_salinan')->firstOrFail();
        $this->assertTrue($copy->hasPermissionTo('ViewAny:Ballot'));
        $this->assertSame(0, $copy->users()->count());
    }

    public function test_duplicate_picks_a_free_name_and_is_hidden_for_super_admin(): void
    {
        $source = $this->role('panitia');
        $this->role('panitia_salinan');
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(ListRoles::class)
            ->assertTableActionHidden('duplicate', Role::where('name', 'super_admin')->firstOrFail())
            ->callTableAction('duplicate', $source);

        $this->assertDatabaseHas('roles', ['name' => 'panitia_salinan_2']);
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole($this->role('super_admin'));

        return $admin;
    }
}
