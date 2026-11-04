<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\UserStats;
use App\Models\Ballot;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\VoterCardIssuer;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_voters_who_have_and_have_not_voted(): void
    {
        $voted = User::factory()->create(['is_admin' => false]);
        User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voted->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(UserStats::class)
            ->assertSee('Pemilih terdaftar')
            ->assertSee('Sudah memilih')
            ->assertSee('Belum memilih');
    }

    public function test_table_shows_each_users_roles_and_voting_status(): void
    {
        $voter = User::factory()->create(['name' => 'Siti Voter', 'is_admin' => false]);
        $voter->assignRole($this->role('panitia'));
        Ballot::create(['user_id' => $voter->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$voter])
            ->assertSee('Panitia')
            ->assertSee('Sudah memilih');
    }

    public function test_role_filter_narrows_the_list(): void
    {
        $panitia = User::factory()->create(['is_admin' => false]);
        $panitia->assignRole($this->role('panitia'));
        $plain = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->filterTable('roles', [$this->role('panitia')->getKey()])
            ->assertCanSeeTableRecords([$panitia])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_admin_access_filter_separates_panel_admins_from_voters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->filterTable('is_admin', true)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$voter]);
    }

    public function test_user_can_be_created_with_roles(): void
    {
        $panitia = $this->role('panitia');

        Livewire::actingAs($this->superAdmin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Budi Panitia',
                'email' => 'budi@example.test',
                'password' => 'rahasia-banget',
                'roles' => [$panitia->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(User::where('email', 'budi@example.test')->firstOrFail()->hasRole('panitia'));
    }

    public function test_admin_cannot_revoke_their_own_admin_access(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['is_admin' => false])
            ->call('save');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_cannot_change_their_own_roles(): void
    {
        $admin = $this->superAdmin();
        $panitia = $this->role('panitia');

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['roles' => [$panitia->getKey()]])
            ->call('save');

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
        $this->assertFalse($admin->fresh()->hasRole('panitia'));
    }

    public function test_voter_who_already_voted_cannot_be_made_an_admin(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voter->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditUser::class, ['record' => $voter->getKey()])
            ->fillForm(['is_admin' => true])
            ->call('save');

        $this->assertFalse($voter->fresh()->is_admin);
    }

    public function test_voter_who_has_not_voted_can_be_made_an_admin(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditUser::class, ['record' => $voter->getKey()])
            ->fillForm(['is_admin' => true])
            ->call('save');

        $this->assertTrue($voter->fresh()->is_admin);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_voter_who_already_voted_cannot_be_deleted_in_bulk(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voter->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$voter]);

        $this->assertModelExists($voter);
        $this->assertDatabaseCount('ballots', 1);
    }

    public function test_voter_who_has_not_voted_can_be_deleted(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$voter]);

        $this->assertModelMissing($voter);
    }

    public function test_voters_can_be_exported_to_csv(): void
    {
        User::factory()->create(['name' => 'Siti Voter', 'email' => 'siti.voter@example.test', 'is_admin' => false]);

        $component = Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->assertActionExists('export')
            ->callAction('export')
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('daftar-pemilih.csv');

        $this->assertStringContainsString('siti.voter@example.test', $this->downloadedContent($component));
    }

    public function test_admins_who_are_not_super_admins_see_voter_names_but_not_emails(): void
    {
        $voter = User::factory()->create(['name' => 'Siti Voter', 'email' => 'siti.voter@example.test', 'is_admin' => false]);

        $component = Livewire::actingAs($this->panitia())
            ->test(ListUsers::class)
            ->assertSee('Siti Voter')
            ->assertDontSee('siti.voter@example.test')
            ->assertTableActionHidden('changeEmail', $voter)
            ->callAction('export');

        $content = $this->downloadedContent($component);
        $this->assertStringContainsString('Siti Voter', $content);
        $this->assertStringNotContainsString('siti.voter@example.test', $content);
    }

    private function downloadedContent($component): string
    {
        return base64_decode(data_get($component->effects, 'download.content'));
    }

    private function panitia(): User
    {
        $role = $this->role('panitia');
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:User', 'guard_name' => 'web']));

        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_users_page_renders_status_tabs_with_counts(): void
    {
        User::factory()->create(['is_admin' => false]);

        $this->actingAs($this->superAdmin())
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('fi-tabs', escape: false)
            ->assertSee('Belum memilih')
            ->assertSee('Sudah memilih')
            ->assertSee('Admin panel');
    }

    public function test_not_voted_tab_lists_voters_without_a_ballot(): void
    {
        $voted = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voted->id]);
        $pending = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->set('activeTab', 'not_voted')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$voted]);
    }

    public function test_admins_tab_lists_only_panel_admins(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->set('activeTab', 'admins')
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$voter]);
    }

    public function test_bulk_role_assignment_adds_roles_to_selected_users_and_skips_own_account(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['is_admin' => false]);
        $panitia = $this->role('panitia');

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('assignRoles', [$voter, $admin], ['roles' => [$panitia->getKey()]])
            ->assertHasNoTableBulkActionErrors();

        $this->assertTrue($voter->fresh()->hasRole('panitia'));
        $this->assertFalse($admin->fresh()->hasRole('panitia'));
    }

    public function test_bulk_role_assignment_keeps_existing_roles(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);
        $voter->assignRole($this->role('panitia'));

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableBulkAction('assignRoles', [$voter], ['roles' => [$this->role('saksi')->getKey()]]);

        $this->assertTrue($voter->fresh()->hasRole('panitia'));
        $this->assertTrue($voter->fresh()->hasRole('saksi'));
    }

    public function test_admin_can_reset_a_voters_password(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableAction('resetPassword', $voter, ['password' => 'password-baru-1'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('password-baru-1', $voter->fresh()->password));
    }

    public function test_password_reset_rejects_short_passwords_and_is_hidden_for_own_account(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('resetPassword', $admin)
            ->callTableAction('resetPassword', $voter, ['password' => 'pendek'])
            ->assertHasTableActionErrors(['password']);
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_signing_in_records_when_the_account_last_signed_in(): void
    {
        $voter = User::factory()->create();
        $this->assertNull($voter->last_login_at);

        Auth::guard('web')->login($voter);

        $this->assertNotNull($voter->fresh()->last_login_at);
    }

    public function test_suspended_account_cannot_use_either_panel(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['suspended_at' => now()]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        $admin->update(['suspended_at' => now()]);
        $this->assertFalse($admin->fresh()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($voter->canAccessPanel(Filament::getPanel('pemilih')));
    }

    public function test_toggle_suspension_suspends_then_reactivates_a_voter(): void
    {
        $voter = User::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableAction('toggleSuspension', $voter);

        $this->assertNotNull($voter->fresh()->suspended_at);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableAction('toggleSuspension', $voter);

        $this->assertNull($voter->fresh()->suspended_at);
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('toggleSuspension', $admin);
    }

    public function test_bulk_suspend_skips_the_acting_admin(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('suspendSelected', [$admin, $voter]);

        $this->assertNull($admin->fresh()->suspended_at);
        $this->assertNotNull($voter->fresh()->suspended_at);
    }

    public function test_suspended_voters_are_left_out_of_printed_cards(): void
    {
        $suspended = User::factory()->create(['suspended_at' => now()]);

        $this->assertSame(0, app(VoterCardIssuer::class)->issue(collect([$suspended])));
    }

    public function test_single_card_resets_the_password_and_opens_the_card_page(): void
    {
        $voter = User::factory()->create();
        $before = $voter->password;

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableAction('printCard', $voter)
            ->assertRedirect(route('voter-cards.print'));

        $this->assertNotSame($before, $voter->fresh()->password);
    }

    public function test_bulk_remove_roles_takes_only_the_chosen_roles_off_voters(): void
    {
        $voter = User::factory()->create();
        $voter->assignRole($this->role('panitia'));
        $voter->assignRole($this->role('pengawas'));

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableBulkAction('removeRoles', [$voter], data: ['roles' => [$this->role('panitia')->getKey()]]);

        $voter->refresh();
        $this->assertFalse($voter->hasRole('panitia'));
        $this->assertTrue($voter->hasRole('pengawas'));
    }

    public function test_registered_filters_narrow_the_list_by_preset_and_by_range(): void
    {
        $recent = User::factory()->create(['created_at' => now()->subDays(2)]);
        $older = User::factory()->create(['created_at' => now()->subDays(40)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->filterTable('registered', 'week')
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$older])
            ->resetTableFilters()
            ->filterTable('registered_range', ['from' => now()->subDays(50)->toDateString(), 'until' => now()->subDays(30)->toDateString()])
            ->assertCanSeeTableRecords([$older])
            ->assertCanNotSeeTableRecords([$recent]);
    }

    public function test_status_filter_separates_suspended_accounts(): void
    {
        $active = User::factory()->create();
        $suspended = User::factory()->create(['suspended_at' => now()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->filterTable('suspended', true)
            ->assertCanSeeTableRecords([$suspended])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_stats_show_turnout_as_a_bar_and_count_suspended_accounts(): void
    {
        $voted = User::factory()->create();
        User::factory()->create(['suspended_at' => now()]);
        Ballot::create(['user_id' => $voted->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(UserStats::class)
            ->assertSeeHtml('aria-valuenow="50"')
            ->assertSee('Dinonaktifkan');
    }

    public function test_row_is_marked_when_a_voter_has_just_voted_and_when_suspended(): void
    {
        User::factory()->create(['suspended_at' => now()]);
        Ballot::create(['user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->assertSeeHtml('admin-row-enter')
            ->assertSeeHtml('admin-user-suspended');
    }

    public function test_name_cell_marks_admin_accounts_and_roles_are_shown_as_chips(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSeeHtml('admin-person-chip')
            ->assertSeeHtml('admin-role-chip is-super');
    }

    public function test_password_field_describes_how_strong_the_password_is(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateUser::class)
            ->set('data.password', 'abc')
            ->assertSee('Kekuatan: lemah. Minimal 8 karakter.')
            ->set('data.password', 'abcdefghijklmnop')
            ->assertSee('Kekuatan: cukup.')
            ->set('data.password', 'abcdef123456789')
            ->assertSee('Kekuatan: kuat.');
    }

    public function test_generated_password_is_long_enough_to_pass_the_form(): void
    {
        $test = Livewire::actingAs($this->superAdmin())
            ->test(CreateUser::class)
            ->callFormComponentAction('password', 'generatePassword');

        $this->assertSame(12, mb_strlen($test->get('data.password')));
    }

    public function test_role_field_says_how_many_permissions_the_chosen_roles_give(): void
    {
        $role = $this->role('panitia');
        $role->givePermissionTo(Permission::create(['name' => 'lihat_contoh', 'guard_name' => 'web']));

        Livewire::actingAs($this->superAdmin())
            ->test(CreateUser::class)
            ->set('data.roles', [$role->getKey()])
            ->assertSee('Peran yang dipilih memberi 1 izin.');
    }

    public function test_detail_page_offers_the_same_voter_actions_as_the_row_menu(): void
    {
        $voter = User::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(ViewUser::class, ['record' => $voter->getKey()])
            ->assertActionExists('printCard')
            ->assertActionExists('toggleSuspension')
            ->assertActionExists('resetPassword');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole($this->role('super_admin'));

        return $admin;
    }
}
