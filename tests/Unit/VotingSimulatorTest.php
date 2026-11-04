<?php

namespace Tests\Unit;

use App\Services\BallotService;
use App\Services\VotingSimulator;
use Tests\TestCase;

class VotingSimulatorTest extends TestCase
{
    public function test_cumulative_weights_rise_and_end_at_one_so_every_roll_picks_a_candidate(): void
    {
        $weights = VotingSimulator::cumulativeWeights(24);

        $this->assertCount(24, $weights);
        $this->assertEqualsWithDelta(1.0, end($weights), 0.000001);

        foreach (array_slice($weights, 1) as $index => $weight) {
            $this->assertGreaterThan($weights[$index], $weight);
        }
    }

    public function test_the_opening_surge_arrives_in_the_first_eight_percent_of_the_window(): void
    {
        $offset = VotingSimulator::sentAtOffset(28800, 0.1, 0.99);

        $this->assertLessThan(0.08 * 28800, $offset);
    }

    public function test_the_closing_rush_arrives_in_the_last_tenth_of_the_window(): void
    {
        $offset = VotingSimulator::sentAtOffset(28800, 0.3, 0.5);

        $this->assertGreaterThanOrEqual(0.9 * 28800, $offset);
        $this->assertLessThan(28800, $offset);
    }

    public function test_send_times_never_fall_outside_the_window(): void
    {
        mt_srand(1);

        foreach (range(1, 2000) as $_) {
            $offset = VotingSimulator::sentAtOffset(28800, mt_rand() / mt_getrandmax(), mt_rand() / mt_getrandmax());

            $this->assertGreaterThanOrEqual(0, $offset);
            $this->assertLessThan(28800, $offset);
        }
    }

    public function test_no_candidate_is_picked_in_more_fields_than_a_ballot_allows(): void
    {
        mt_srand(2);
        $fieldIds = [1, 2, 3, 4];
        $candidateIds = [11, 12, 13];
        $weights = VotingSimulator::cumulativeWeights(count($candidateIds));

        foreach (range(1, 2000) as $_) {
            $picks = VotingSimulator::picks($fieldIds, $candidateIds, $weights, fn (): float => mt_rand() / (mt_getrandmax() + 1));

            $perCandidate = array_count_values($picks);

            foreach ($perCandidate as $count) {
                $this->assertLessThanOrEqual(BallotService::MAX_FIELDS_PER_CANDIDATE, $count);
            }
        }
    }

    public function test_every_ballot_has_at_least_one_pick_and_only_fields_that_exist(): void
    {
        mt_srand(3);
        $fieldIds = [1, 2, 3, 4];
        $candidateIds = range(11, 34);
        $weights = VotingSimulator::cumulativeWeights(count($candidateIds));

        foreach (range(1, 2000) as $_) {
            $picks = VotingSimulator::picks($fieldIds, $candidateIds, $weights, fn (): float => mt_rand() / (mt_getrandmax() + 1));

            $this->assertNotEmpty($picks);
            $this->assertEmpty(array_diff(array_keys($picks), $fieldIds));
            $this->assertEmpty(array_diff(array_values($picks), $candidateIds));
        }
    }

    public function test_the_most_popular_candidate_gets_more_picks_than_the_least_popular(): void
    {
        mt_srand(4);
        $candidateIds = range(11, 34);
        $weights = VotingSimulator::cumulativeWeights(count($candidateIds));
        $counts = array_fill_keys($candidateIds, 0);

        foreach (range(1, 5000) as $_) {
            foreach (VotingSimulator::picks([1], $candidateIds, $weights, fn (): float => mt_rand() / (mt_getrandmax() + 1)) as $candidateId) {
                $counts[$candidateId]++;
            }
        }

        $this->assertGreaterThan($counts[34], $counts[11]);
    }
}
