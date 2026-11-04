<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Field;
use App\Models\User;
use App\Models\VoteCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoteCorrection>
 */
class VoteCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'field_id' => Field::factory(),
            'user_id' => User::factory()->state(['is_admin' => true]),
            'amount' => fake()->numberBetween(1, 10),
            'reason' => fake()->sentence(),
        ];
    }
}
