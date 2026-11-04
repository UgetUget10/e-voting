<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\BallotNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BallotNote>
 */
class BallotNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ballot_id' => Ballot::factory(),
            'user_id' => User::factory()->state(['is_admin' => true]),
            'body' => fake()->sentence(),
        ];
    }
}
