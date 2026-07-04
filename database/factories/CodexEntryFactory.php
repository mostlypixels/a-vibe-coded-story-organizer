<?php

namespace Database\Factories;

use App\Enums\CodexEntryType;
use App\Models\CodexEntry;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodexEntry>
 */
class CodexEntryFactory extends Factory
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
            'type' => CodexEntryType::Character,
            'name' => fake()->name(),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    public function character(): static
    {
        return $this->state(['type' => CodexEntryType::Character]);
    }

    public function location(): static
    {
        return $this->state(['type' => CodexEntryType::Location]);
    }

    public function organization(): static
    {
        return $this->state(['type' => CodexEntryType::Organization]);
    }
}
