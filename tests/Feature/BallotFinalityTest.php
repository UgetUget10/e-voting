<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class BallotFinalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_submitted_ballot_cannot_be_deleted_even_by_a_super_admin(): void
    {
        $ballot = $this->submittedBallot();
        $superAdmin = User::factory()->create(['is_admin' => true]);
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $this->actingAs($superAdmin)->delete('/admin/ballots/'.$ballot->id)->assertNotFound();

        $this->assertThrows(fn () => $ballot->delete(), LogicException::class);
        $this->assertDatabaseCount('ballots', 1);
        $this->assertDatabaseCount('votes', 2);
    }

    public function test_a_voter_who_has_voted_cannot_be_deleted_with_their_ballot(): void
    {
        $voter = User::factory()->create();
        $this->submittedBallot($voter);

        $this->assertThrows(fn () => $voter->delete(), LogicException::class);
        $this->assertModelExists($voter);
        $this->assertDatabaseCount('ballots', 1);
    }

    public function test_a_voter_who_has_voted_cannot_delete_their_account_from_their_profile(): void
    {
        $voter = User::factory()->create(['password' => 'secret-pass']);
        $this->submittedBallot($voter);

        $this->actingAs($voter)
            ->from('/profile')
            ->delete('/profile', ['password' => 'secret-pass'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertModelExists($voter);
        $this->assertDatabaseCount('ballots', 1);
        $this->assertDatabaseCount('votes', 2);
    }

    public function test_a_voter_who_has_not_voted_can_still_delete_their_account(): void
    {
        $voter = User::factory()->create(['password' => 'secret-pass']);

        $this->actingAs($voter)
            ->delete('/profile', ['password' => 'secret-pass'])
            ->assertRedirect('/');

        $this->assertModelMissing($voter);
    }

    public function test_a_candidate_who_was_picked_cannot_be_deleted(): void
    {
        $this->submittedBallot();
        $candidate = Candidate::firstOrFail();

        $this->assertThrows(fn () => $candidate->delete(), LogicException::class);
        $this->assertModelExists($candidate);
        $this->assertDatabaseCount('votes', 2);
    }

    public function test_a_field_with_votes_cannot_be_deleted(): void
    {
        $this->submittedBallot();
        $field = Field::firstOrFail();

        $this->assertThrows(fn () => $field->delete(), LogicException::class);
        $this->assertModelExists($field);
        $this->assertDatabaseCount('votes', 2);
    }

    public function test_unpicked_candidates_and_empty_fields_can_still_be_deleted(): void
    {
        $candidate = Candidate::create(['name' => 'Belum dipilih']);
        $field = Field::create(['name' => 'Kosong', 'position' => 1]);

        $candidate->delete();
        $field->delete();

        $this->assertModelMissing($candidate);
        $this->assertModelMissing($field);
    }

    private function submittedBallot(?User $voter = null): Ballot
    {
        $voter ??= User::factory()->create();
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $candidate = Candidate::create(['name' => 'Andi']);

        $ballot = Ballot::create(['user_id' => $voter->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $presiden->id, 'candidate_id' => $candidate->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $bendahara->id, 'candidate_id' => $candidate->id]);

        return $ballot;
    }
}
