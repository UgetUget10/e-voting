<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Elections\Pages\ListElections;
use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Mail\VotingReminderMail;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Role;
use App\Models\User;
use App\Services\BallotIntegrityCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLockAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        return User::factory()->create(['is_admin' => true])->assignRole($role);
    }

    public function test_locking_an_election_blocks_candidate_and_field_changes(): void
    {
        $admin = $this->superAdmin();
        $election = Election::create(['name' => 'Pemilu']);
        $this->actingAs($admin);

        $this->assertTrue(CandidateResource::canCreate());

        Livewire::test(ListElections::class)->callTableAction('lock', $election);

        $this->assertNotNull($election->fresh()->locked_at);
        $this->assertFalse(CandidateResource::canCreate());
        $this->assertFalse(FieldResource::canCreate());

        Livewire::test(ListElections::class)->callTableAction('unlock', $election);

        $this->assertNull($election->fresh()->locked_at);
        $this->assertTrue(FieldResource::canCreate());
    }

    public function test_integrity_check_reports_a_ballot_without_picks(): void
    {
        Ballot::create(['user_id' => User::factory()->create()->id]);

        $report = app(BallotIntegrityCheck::class)->run();

        $this->assertSame(1, $report['withoutPicks']);
        $this->assertSame(1, $report['total']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('verifyIntegrity')
            ->assertNotified('1 masalah ditemukan.');
    }

    public function test_activity_log_exports_csv(): void
    {
        $admin = $this->superAdmin();
        AdminActivity::create(['user_id' => $admin->id, 'description' => 'Contoh tindakan']);

        Livewire::actingAs($admin)
            ->test(ListAdminActivities::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function test_bulk_reminder_skips_admins_and_voters_who_already_voted(): void
    {
        Mail::fake();
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);
        $waiting = User::factory()->create();
        $done = User::factory()->create();
        Ballot::create(['user_id' => $done->id]);
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('remindSelected', [$waiting, $done, $admin]);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(VotingReminderMail::class, fn ($mail) => $mail->hasTo($waiting->email));
    }
}
