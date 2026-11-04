<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountSecurity;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function insertSession(User $user, string $sessionId, string $userAgent = self::CHROME_ON_WINDOWS): void
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_admin_can_open_the_security_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/account-security')
            ->assertOk()
            ->assertSee('Verifikasi dua langkah lewat email');
    }

    public function test_session_list_names_the_device_of_each_sign_in(): void
    {
        $admin = $this->admin();
        $this->insertSession($admin, 'session-chrome', self::CHROME_ON_WINDOWS);

        $this->actingAs($admin)
            ->get('/admin/account-security')
            ->assertOk()
            ->assertSee('Chrome di Windows')
            ->assertSee('203.0.113.7');
    }

    public function test_session_list_does_not_show_sign_ins_of_other_admins(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $this->insertSession($otherAdmin, 'session-other', 'Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0');

        $this->actingAs($admin)
            ->get('/admin/account-security')
            ->assertOk()
            ->assertDontSee('Firefox di Linux');
    }

    public function test_admin_can_end_another_sign_in_but_not_the_current_one(): void
    {
        $admin = $this->admin();
        session()->start();
        $currentId = session()->getId();
        $this->insertSession($admin, $currentId);
        $this->insertSession($admin, 'session-other');

        Livewire::actingAs($admin)
            ->test(AccountSecurity::class)
            ->call('terminateSession', 'session-other')
            ->call('terminateSession', $currentId);

        $this->assertDatabaseMissing('sessions', ['id' => 'session-other']);
        $this->assertDatabaseHas('sessions', ['id' => $currentId]);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengakhiri satu sesi masuk lain.']);
    }

    public function test_admin_can_end_all_other_sign_ins_and_keep_the_current_one(): void
    {
        $admin = $this->admin();
        session()->start();
        $currentId = session()->getId();
        $this->insertSession($admin, $currentId);
        $this->insertSession($admin, 'session-one');
        $this->insertSession($admin, 'session-two');

        Livewire::actingAs($admin)
            ->test(AccountSecurity::class)
            ->call('terminateOtherSessions');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-two']);
        $this->assertDatabaseHas('sessions', ['id' => $currentId]);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengakhiri 2 sesi masuk lain.']);
    }

    public function test_an_admin_cannot_end_another_admins_sign_in(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $this->insertSession($otherAdmin, 'session-other-admin');

        Livewire::actingAs($admin)
            ->test(AccountSecurity::class)
            ->call('terminateSession', 'session-other-admin');

        $this->assertDatabaseHas('sessions', ['id' => 'session-other-admin']);
    }

    public function test_email_authentication_is_available_to_admin_panel_and_starts_disabled(): void
    {
        $admin = $this->admin();

        $this->assertCount(1, Filament::getPanel('admin')->getMultiFactorAuthenticationProviders());
        $this->assertFalse($admin->hasEmailAuthentication());

        $admin->toggleEmailAuthentication(true);

        $this->assertTrue($admin->fresh()->hasEmailAuthentication());
    }
}
