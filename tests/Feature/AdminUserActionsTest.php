<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Mail\VotingReminderMail;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_shows_whether_a_voter_has_voted_but_never_the_picks(): void
    {
        $voter = User::factory()->create(['name' => 'Pemilih Rahasia']);
        $field = Field::create(['name' => 'Presiden']);
        $andi = Candidate::create(['name' => 'Andi Kandidat']);
        $ballot = Ballot::create(['user_id' => $voter->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $andi->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewUser::class, ['record' => $voter->getKey()])
            ->assertSee('Pemilih Rahasia')
            ->assertSee('Sudah memilih')
            ->assertDontSee('Andi Kandidat')
            ->assertDontSee('Presiden');
    }

    public function test_admin_can_change_a_voters_email_after_confirming_with_their_own_password(): void
    {
        $admin = $this->superAdmin();
        $voter = User::factory()->create(['email' => 'lama@example.com']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('changeEmail', $voter, ['email' => 'baru@example.com', 'confirmation' => 'password'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', ['id' => $voter->id, 'email' => 'baru@example.com']);
        $this->assertDatabaseHas('admin_activities', ['user_id' => $admin->id]);
    }

    public function test_changing_an_email_fails_with_a_wrong_admin_password(): void
    {
        $voter = User::factory()->create(['email' => 'lama@example.com']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callTableAction('changeEmail', $voter, ['email' => 'baru@example.com', 'confirmation' => 'salah-sekali'])
            ->assertHasTableActionErrors(['confirmation']);

        $this->assertDatabaseHas('users', ['id' => $voter->id, 'email' => 'lama@example.com']);
    }

    public function test_reminder_is_queued_only_for_voters_who_have_not_voted(): void
    {
        Mail::fake();
        Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $pending = User::factory()->count(2)->create(['is_admin' => false]);
        $voted = User::factory()->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voted->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callAction('remind')
            ->assertHasNoActionErrors();

        Mail::assertQueued(VotingReminderMail::class, 2);
        Mail::assertQueued(VotingReminderMail::class, fn (VotingReminderMail $mail): bool => $mail->hasTo($pending[0]->email));
        Mail::assertNotQueued(VotingReminderMail::class, fn (VotingReminderMail $mail): bool => $mail->hasTo($voted->email));
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengirim pengingat ke 2 pemilih yang belum memilih.']);
    }

    public function test_reminder_is_not_sent_when_no_voting_is_running(): void
    {
        Mail::fake();
        Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);
        User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callAction('remind');

        Mail::assertNothingQueued();
    }

    public function test_reminder_email_names_the_election_and_links_to_the_voter_panel(): void
    {
        $voter = User::factory()->create(['name' => 'Dewi']);
        $election = Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $mail = new VotingReminderMail($voter, $election);

        $this->assertStringContainsString('Ketua OSIS', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Halo, Dewi');
        $mail->assertSeeInHtml('Ketua OSIS');
        $mail->assertSeeInHtml(url('/pemilih'));
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
