<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ballot>
 */
class BallotFactory extends Factory
{
    /**
     * Define the model's default state. The receipt code is filled in by the model when it is created.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['is_admin' => false]),
        ];
    }

    public function needsReview(): static
    {
        return $this->state(['needs_review' => true]);
    }
}
