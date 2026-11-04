<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveBallotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_ballots_are_dated_now_and_keep_the_election_open(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 30, '--candidates' => 21, '--fields' => 2, '--turnout' => 50, '--hours' => 4])
            ->assertSuccessful();
        $before = Ballot::query()->count();

        $this->artisan('simulasi:suara-masuk', ['--jumlah' => 5, '--putaran' => 1])
            ->assertSuccessful();

        $this->assertSame($before + 5, Ballot::query()->count());
        $this->assertSame(5, Ballot::query()->where('created_at', '>=', now()->subMinutes(2))->count());
        $this->assertTrue(Election::query()->sole()->isOpen());
    }

    public function test_no_voter_ever_gets_a_second_ballot(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 30, '--candidates' => 21, '--fields' => 2, '--turnout' => 50, '--hours' => 4])
            ->assertSuccessful();

        $this->artisan('simulasi:suara-masuk', ['--jumlah' => 1000, '--putaran' => 1])
            ->assertSuccessful();

        $this->assertSame(30, Ballot::query()->count());
        $this->assertSame(Ballot::query()->distinct()->count('user_id'), Ballot::query()->count());
    }

    public function test_it_needs_a_simulated_election_first(): void
    {
        $this->artisan('simulasi:suara-masuk', ['--jumlah' => 5, '--putaran' => 1])
            ->assertFailed();
    }
}
