<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'event_datetime' => fake()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
