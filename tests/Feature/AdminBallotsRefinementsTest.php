<?php

namespace Tests\Feature;

use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Models\Ballot;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminActivityLog;
use App\Services\BallotReviewUndo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBallotsRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_list_without_filters_says_no_ballots_have_arrived(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee('Belum ada suara masuk');
    }

    public function test_a_search_with_no_match_says_so_and_clearing_the_filters_brings_the_list_back(): void
    {
        $this->ballot();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->set('tableSearch', 'ZZZZZZZZ')
            ->assertSee('Tidak ada suara yang cocok')
            ->assertDontSee('Belum ada suara masuk')
            ->call('resetBallotFilters')
            ->assertDontSee('Tidak ada suara yang cocok');
    }

    public function test_choosing_a_tab_with_no_ballots_counts_as_a_filter_in_the_empty_state(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->set('activeTab', 'review')
            ->assertSee('Tidak ada suara yang cocok');
    }

    public function test_the_search_is_remembered_when_the_page_is_opened_again(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(ListBallots::class)->set('tableSearch', 'ANDI');

        Livewire::actingAs($admin)->test(ListBallots::class)->assertSet('tableSearch', 'ANDI');
    }

    public function test_the_detail_modal_steps_to_the_neighbouring_ballot_on_the_page(): void
    {
        $admin = $this->superAdmin();
        $newest = $this->ballot(now()->subMinutes(1));
        $middle = $this->ballot(now()->subMinutes(2));
        $oldest = $this->ballot(now()->subMinutes(3));

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->mountTableAction('view', $middle)
            ->assertMountedActionModalSee(['Suara ke 2 dari 3'])
            ->call('replaceMountedAction', 'view', [], ['table' => true, 'recordKey' => $oldest->getKey()])
            ->assertMountedActionModalSee(['Suara ke 3 dari 3'])
            ->call('replaceMountedAction', 'view', [], ['table' => true, 'recordKey' => $newest->getKey()])
            ->assertMountedActionModalSee(['Suara ke 1 dari 3']);

        foreach ([$middle, $oldest, $newest] as $ballot) {
            $this->assertDatabaseHas('admin_activities', ['description' => 'Membuka identitas pemilih pada surat suara '.$ballot->receipt_code.'.']);
        }
    }

    public function test_the_last_ballot_on_the_page_reports_its_place_in_the_list(): void
    {
        $admin = $this->superAdmin();
        $this->ballot(now()->subMinutes(1));
        $oldest = $this->ballot(now()->subMinutes(2));

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->mountTableAction('view', $oldest)
            ->assertMountedActionModalSee(['Suara ke 2 dari 2']);
    }

    public function test_a_review_change_appears_in_that_ballots_history(): void
    {
        $admin = $this->superAdmin();
        $ballot = $this->ballot();

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->callTableAction('toggleReview', $ballot)
            ->mountTableAction('view', $ballot)
            ->assertMountedActionModalSee(['Surat suara dikirim.', 'Menandai surat suara '.$ballot->receipt_code.' untuk ditinjau.']);

        $this->assertDatabaseHas('admin_activities', [
            'subject_type' => Ballot::class,
            'subject_id' => $ballot->id,
            'description' => 'Menandai surat suara '.$ballot->receipt_code.' untuk ditinjau.',
        ]);
    }

    public function test_a_note_is_recorded_against_its_ballot(): void
    {
        $admin = $this->superAdmin();
        $ballot = $this->ballot();

        Livewire::actingAs($admin)
            ->test(ListBallots::class)
            ->callTableAction('note', $ballot, data: ['body' => 'Perlu dicek ulang.']);

        $this->assertDatabaseHas('admin_activities', [
            'subject_type' => Ballot::class,
            'subject_id' => $ballot->id,
            'description' => 'Menambahkan catatan pada surat suara '.$ballot->receipt_code.'.',
        ]);
    }

    public function test_a_review_change_is_undone_by_the_admin_who_made_it(): void
    {
        $admin = $this->superAdmin();
        $ballot = $this->ballot();

        $this->actingAs($admin);
        $token = app(BallotReviewUndo::class)->remember([$ballot->id => false]);
        $ballot->update(['needs_review' => true]);

        Livewire::actingAs($admin)->test(ListBallots::class)->dispatch('ballots-review-undo', $token);

        $this->assertFalse($ballot->fresh()->needs_review);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Membatalkan perubahan tanda tinjauan pada 1 surat suara.']);
    }

    public function test_another_admin_cannot_undo_a_review_change(): void
    {
        $admin = $this->superAdmin();
        $ballot = $this->ballot();

        $this->actingAs($admin);
        $token = app(BallotReviewUndo::class)->remember([$ballot->id => false]);
        $ballot->update(['needs_review' => true]);

        $this->actingAs($this->superAdmin());
        $restored = app(BallotReviewUndo::class)->restore($token, app(AdminActivityLog::class));

        $this->assertNull($restored);
        $this->assertTrue($ballot->fresh()->needs_review);
    }

    public function test_an_undo_is_refused_once_the_window_has_passed(): void
    {
        $admin = $this->superAdmin();
        $ballot = $this->ballot();

        $this->actingAs($admin);
        $token = app(BallotReviewUndo::class)->remember([$ballot->id => false]);
        $ballot->update(['needs_review' => true]);

        $this->travel(BallotReviewUndo::MINUTES + 1)->minutes();

        $this->assertNull(app(BallotReviewUndo::class)->restore($token, app(AdminActivityLog::class)));
        $this->assertTrue($ballot->fresh()->needs_review);
    }

    public function test_a_bulk_change_is_restored_flag_by_flag(): void
    {
        $admin = $this->superAdmin();
        $flagged = $this->ballot();
        $flagged->update(['needs_review' => true]);
        $clear = $this->ballot();

        $this->actingAs($admin);
        $token = app(BallotReviewUndo::class)->remember([$flagged->id => true, $clear->id => false]);
        $flagged->update(['needs_review' => false]);
        $clear->update(['needs_review' => true]);

        app(BallotReviewUndo::class)->restore($token, app(AdminActivityLog::class));

        $this->assertTrue($flagged->fresh()->needs_review);
        $this->assertFalse($clear->fresh()->needs_review);
    }

    private function ballot(?\DateTimeInterface $sentAt = null): Ballot
    {
        $ballot = Ballot::create([
            'user_id' => User::factory()->create(['is_admin' => false])->id,
        ]);

        if ($sentAt !== null) {
            $ballot->forceFill(['created_at' => $sentAt])->save();
        }

        return $ballot;
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
