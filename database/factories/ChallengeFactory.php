<?php

namespace Database\Factories;

use App\Enums\ChallengeRecurrence;
use App\Models\Challenge;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    /**
     * Default state is a fixed challenge: `recurrence` none, `ends_on` set.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-2 months', '-1 month');

        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'recurrence' => ChallengeRecurrence::None,
            'starts_on' => $startsOn,
            'ends_on' => (clone $startsOn)->modify('+29 days'),
            'target_words' => fake()->numberBetween(10000, 50000),
        ];
    }

    public function monthly(): static
    {
        return $this->state([
            'recurrence' => ChallengeRecurrence::Monthly,
            'ends_on' => null,
        ]);
    }
}
