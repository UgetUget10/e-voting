<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Models\Vote;
use App\Services\BallotService;
use App\Services\ResultService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VotingRulesTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Field $osis;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create([
            'name' => 'Test',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->osis = Field::create(['name' => 'Ketua OSIS']);
    }

    public function test_voter_can_pick_a_different_candidate_in_each_field(): void
    {
        $jokowi = $this->candidate('Jokowi');
        $gibran = $this->candidate('Gibran');
        $prabowo = $this->candidate('Prabowo');
        $third = Field::create(['name' => 'Bendahara']);

        app(BallotService::class)->submit(User::factory()->create(), [
            $this->presiden->id => $jokowi->id,
            $this->osis->id => $gibran->id,
            $third->id => $prabowo->id,
        ]);

        $this->assertDatabaseCount('ballots', 1);
        $this->assertDatabaseCount('votes', 3);
    }

    public function test_voter_must_pick_at_least_one_candidate(): void
    {
        $this->expectException(ValidationException::class);

        app(BallotService::class)->submit(User::factory()->create(), [
            $this->presiden->id => null,
        ]);
    }

    public function test_same_candidate_can_be_picked_in_two_fields(): void
    {
        $jokowi = $this->candidate('Jokowi');

        app(BallotService::class)->submit(User::factory()->create(), [
            $this->presiden->id => $jokowi->id,
            $this->osis->id => $jokowi->id,
        ]);

        $this->assertDatabaseCount('votes', 2);
    }

    public function test_same_candidate_cannot_be_picked_in_more_than_two_fields(): void
    {
        $jokowi = $this->candidate('Jokowi');
        $third = Field::create(['name' => 'Bendahara']);

        $this->expectException(ValidationException::class);

        app(BallotService::class)->submit(User::factory()->create(), [
            $this->presiden->id => $jokowi->id,
            $this->osis->id => $jokowi->id,
            $third->id => $jokowi->id,
        ]);
    }

    public function test_voter_cannot_submit_more_than_one_ballot(): void
    {
        $jokowi = $this->candidate('Jokowi');
        $user = User::factory()->create();
        $picks = [$this->presiden->id => $jokowi->id];

        app(BallotService::class)->submit($user, $picks);

        $this->expectException(ValidationException::class);

        app(BallotService::class)->submit($user, $picks);
    }

    public function test_top_two_per_field_advance(): void
    {
        $a = $this->candidate('A');
        $b = $this->candidate('B');
        $c = $this->candidate('C');

        $this->castVotes($this->presiden, $a, 5);
        $this->castVotes($this->presiden, $b, 3);
        $this->castVotes($this->presiden, $c, 1);

        $this->assertSame(['A', 'B'], $this->winnerNames($this->presiden));
    }

    public function test_candidates_tied_for_second_all_advance_without_a_revote(): void
    {
        // Matches the note: 54 / 36 / 36 => all three go to presentation.
        $jokowi = $this->candidate('Jokowi');
        $gibran = $this->candidate('Gibran');
        $prabowo = $this->candidate('Prabowo');

        $this->castVotes($this->presiden, $jokowi, 54);
        $this->castVotes($this->presiden, $gibran, 36);
        $this->castVotes($this->presiden, $prabowo, 36);

        $this->assertEqualsCanonicalizing(
            ['Jokowi', 'Gibran', 'Prabowo'],
            $this->winnerNames($this->presiden),
        );
    }

    public function test_only_the_top_two_advance_when_the_second_place_is_not_tied(): void
    {
        $a = $this->candidate('A');
        $b = $this->candidate('B');
        $c = $this->candidate('C');

        $this->castVotes($this->presiden, $a, 10);
        $this->castVotes($this->presiden, $b, 7);
        $this->castVotes($this->presiden, $c, 6);

        $this->assertSame(['A', 'B'], $this->winnerNames($this->presiden));
    }

    public function test_candidates_without_votes_never_advance(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->candidate($name);
        }

        $this->assertSame([], $this->winnerNames($this->presiden));
    }

    public function test_second_place_without_votes_does_not_pull_in_the_rest(): void
    {
        $a = $this->candidate('A');
        $this->candidate('B');
        $this->candidate('C');

        $this->castVotes($this->presiden, $a, 4);

        $this->assertSame(['A'], $this->winnerNames($this->presiden));
    }

    public function test_admin_cannot_submit_a_ballot(): void
    {
        $jokowi = $this->candidate('Jokowi');
        $admin = User::factory()->create(['is_admin' => true]);

        try {
            app(BallotService::class)->submit($admin, [$this->presiden->id => $jokowi->id]);
            $this->fail('Admin ballot was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('ballots', 0);
        }
    }

    public function test_unrelated_database_errors_are_not_reported_as_already_voted(): void
    {
        $jokowi = $this->candidate('Jokowi');
        $user = User::factory()->create();

        Vote::creating(fn () => throw new QueryException('sqlite', 'insert into votes', [], new Exception('disk full')));

        $this->expectException(QueryException::class);

        app(BallotService::class)->submit($user, [$this->presiden->id => $jokowi->id]);
    }

    private function candidate(string $name): Candidate
    {
        return Candidate::create(['name' => $name]);
    }

    /**
     * Each vote lives on its own ballot, so the unique ballot-per-user rule never
     * interferes with the counting tests.
     */
    private function castVotes(Field $field, Candidate $candidate, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $ballot = Ballot::create(['user_id' => User::factory()->create()->id]);

            Vote::create([
                'ballot_id' => $ballot->id,
                'field_id' => $field->id,
                'candidate_id' => $candidate->id,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function winnerNames(Field $field): array
    {
        $results = app(ResultService::class);

        return $results
            ->winners($results->candidatesFor($field))
            ->pluck('name')
            ->all();
    }
}
