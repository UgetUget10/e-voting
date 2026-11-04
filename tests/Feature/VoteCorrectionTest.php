<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Filament\Resources\Candidates\RelationManagers\VoteCorrectionsRelationManager;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\BallotReset;
use App\Services\ResultService;
use App\Services\VoteCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VoteCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_correction_stores_the_difference_and_moves_the_result(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 3);

        $correction = app(VoteCorrectionService::class)->correct($andi, $field, 5, 'Salah hitung di TPS 2');

        $this->assertSame(2, $correction->amount);
        $this->assertSame(5, app(VoteCorrectionService::class)->currentTotal($andi, $field));
        $this->assertSame(5, app(ResultService::class)->results()->first()['candidates']->first()->votes_count);
        $this->assertSame(5, app(ResultService::class)->candidatesFor($field)->first()->votes_count);
    }

    public function test_correction_can_lower_the_total(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 3);

        $correction = app(VoteCorrectionService::class)->correct($andi, $field, 1, 'Surat suara ganda');

        $this->assertSame(-2, $correction->amount);
        $this->assertSame(1, $andi->voteTotal());
    }

    public function test_ballots_and_picks_are_left_untouched(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 2);

        app(VoteCorrectionService::class)->correct($andi, $field, 9, 'Koreksi');

        $this->assertDatabaseCount('votes', 2);
        $this->assertDatabaseCount('ballots', 2);
    }

    public function test_a_total_that_is_already_correct_is_rejected(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 2);

        $this->expectException(ValidationException::class);

        app(VoteCorrectionService::class)->correct($andi, $field, 2, 'Tidak ada perubahan');
    }

    public function test_a_negative_total_is_rejected(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);

        $this->expectException(ValidationException::class);

        app(VoteCorrectionService::class)->correct($andi, $field, -1, 'Salah');
    }

    public function test_corrections_are_refused_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);

        $this->expectException(ValidationException::class);

        app(VoteCorrectionService::class)->correct($andi, $field, 4, 'Koreksi');
    }

    public function test_removing_a_correction_restores_the_count(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 3);
        $correction = app(VoteCorrectionService::class)->correct($andi, $field, 7, 'Koreksi');

        app(VoteCorrectionService::class)->remove($correction);

        $this->assertDatabaseCount('vote_corrections', 0);
        $this->assertSame(3, app(VoteCorrectionService::class)->currentTotal($andi, $field));
    }

    public function test_every_correction_is_logged_against_the_candidate(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 1);

        app(VoteCorrectionService::class)->correct($andi, $field, 4, 'Salah input');

        $activity = AdminActivity::query()->where('description', 'like', 'Mengoreksi total suara%')->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('dari 1 menjadi 4', $activity->description);
        $this->assertSame(Candidate::class, $activity->subject_type);
        $this->assertSame($andi->getKey(), $activity->subject_id);
    }

    public function test_a_candidate_with_a_correction_cannot_be_deleted(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        app(VoteCorrectionService::class)->correct($andi, $field, 2, 'Koreksi');

        $this->expectException(\LogicException::class);

        $andi->fresh()->delete();
    }

    public function test_a_field_with_only_a_correction_counts_as_having_votes(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        app(VoteCorrectionService::class)->correct($andi, $field, 2, 'Koreksi');

        $this->assertTrue($field->fresh()->hasVotes());
    }

    public function test_resetting_ballots_also_clears_corrections(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        app(VoteCorrectionService::class)->correct($andi, $field, 2, 'Koreksi');

        app(BallotReset::class)->reset();

        $this->assertDatabaseCount('vote_corrections', 0);
        $this->assertSame(0, app(VoteCorrectionService::class)->currentTotal($andi, $field));
    }

    public function test_correction_from_the_candidate_table_updates_the_total(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($field, $andi, 1);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableAction('correct', $andi, data: [
                'field_id' => $field->getKey(),
                'total' => 6,
                'reason' => 'Salah hitung di TPS 3',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(6, app(VoteCorrectionService::class)->currentTotal($andi, $field));
    }

    public function test_correction_is_listed_and_can_be_undone_from_the_candidate_page(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $correction = app(VoteCorrectionService::class)->correct($andi, $field, 4, 'Salah input');

        Livewire::actingAs($this->superAdmin())
            ->test(VoteCorrectionsRelationManager::class, [
                'ownerRecord' => $andi,
                'pageClass' => ViewCandidate::class,
            ])
            ->assertCanSeeTableRecords([$correction])
            ->callTableAction('remove', $correction)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('vote_corrections', 0);
    }

    private function castVotes(Field $field, Candidate $candidate, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        }
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
