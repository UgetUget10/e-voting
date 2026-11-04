<?php

namespace Tests\Feature;

use App\Filament\Pages\ElectionDashboard;
use App\Filament\Resources\Elections\Pages\ListElections;
use App\Models\Election;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_admins_are_notified_when_voting_is_closed_now(): void
    {
        $actor = $this->superAdmin();
        $otherAdmin = $this->superAdmin();
        $election = Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($actor)
            ->test(ListElections::class)
            ->callTableAction('closeNow', $election);

        $this->assertSame(1, $otherAdmin->notifications()->count());
        $this->assertSame('Menutup voting "Ketua OSIS" sekarang.', $otherAdmin->notifications()->first()->data['title']);
        $this->assertSame(0, $actor->notifications()->count());
    }

    public function test_showing_early_results_notifies_other_admins(): void
    {
        $actor = $this->superAdmin();
        $otherAdmin = $this->superAdmin();
        Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($actor)
            ->test(ElectionDashboard::class)
            ->callAction('toggleResults');

        $this->assertSame(1, $otherAdmin->notifications()->count());
    }

    public function test_suspended_admins_are_not_notified(): void
    {
        $actor = $this->superAdmin();
        $suspendedAdmin = $this->superAdmin();
        $suspendedAdmin->update(['suspended_at' => now()]);

        app(AdminActivityLog::class)->record('Mereset 0 surat suara.', notifyAdmins: true);

        $this->assertSame(0, $suspendedAdmin->notifications()->count());
    }

    public function test_routine_actions_do_not_notify_admins(): void
    {
        $actor = $this->superAdmin();
        $otherAdmin = $this->superAdmin();

        $this->actingAs($actor);
        app(AdminActivityLog::class)->record('Mengekspor hasil voting ke CSV.');

        $this->assertSame(0, $otherAdmin->notifications()->count());
    }

    public function test_notifications_are_sent_without_a_signed_in_admin(): void
    {
        $otherAdmin = $this->superAdmin();

        app(AdminActivityLog::class)->record('Mereset 3 surat suara.', notifyAdmins: true);

        $this->assertSame('Dilakukan oleh sistem.', $otherAdmin->notifications()->first()->data['body']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
