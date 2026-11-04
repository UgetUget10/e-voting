<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VotingSimulationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_fills_voters_candidates_fields_ballots_and_a_schedule(): void
    {
        $this->artisan('simulasi:pemilihan', [
            '--voters' => 60,
            '--candidates' => 21,
            '--fields' => 3,
            '--turnout' => 100,
            '--hours' => 8,
            '--seed' => 7,
        ])->assertSuccessful();

        $this->assertSame(60, User::query()->where('is_admin', false)->count());
        $this->assertSame(21, Candidate::query()->count());
        $this->assertSame(3, Field::query()->count());
        $this->assertSame(60, Ballot::query()->count());
        $this->assertGreaterThanOrEqual(60, Vote::query()->count());
        $this->assertLessThanOrEqual(180, Vote::query()->count());

        $election = Election::query()->sole();
        $this->assertTrue($election->isOpen());
        $this->assertEqualsWithDelta(8 * 3600, $election->opens_at->diffInSeconds($election->closes_at) - 2 * 3600, 5);
    }

    public function test_no_ballot_picks_one_candidate_in_more_fields_than_allowed(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 300, '--candidates' => 6, '--fields' => 4, '--turnout' => 100, '--seed' => 11])
            ->assertSuccessful();

        $overused = Vote::query()
            ->select('ballot_id', 'candidate_id')
            ->groupBy('ballot_id', 'candidate_id')
            ->havingRaw('count(*) > 2')
            ->count();

        $this->assertSame(0, $overused);
    }

    public function test_every_ballot_is_sent_inside_the_voting_window(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 80, '--candidates' => 21, '--fields' => 2, '--turnout' => 100, '--hours' => 4])
            ->assertSuccessful();

        $election = Election::query()->sole();

        $this->assertSame(0, Ballot::query()
            ->where(fn ($query) => $query->where('created_at', '<', $election->opens_at)->orWhere('created_at', '>', now()))
            ->count());
    }

    public function test_it_refuses_to_overwrite_an_existing_election_without_fresh(): void
    {
        Election::create([
            'name' => 'Pemilihan yang sudah ada',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);

        $this->artisan('simulasi:pemilihan', ['--voters' => 10, '--candidates' => 21])
            ->assertFailed();

        $this->assertSame(0, DB::table('users')->where('is_admin', false)->count());
    }

    public function test_it_refuses_fewer_candidates_than_the_fields_need(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 10, '--candidates' => 1, '--fields' => 4])
            ->assertFailed();

        $this->assertSame(0, Election::query()->count());
    }
}
