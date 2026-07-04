<?php

namespace Database\Factories;

use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodexAttribute>
 */
class CodexAttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // position is intentionally omitted: the CodexAttribute::creating() hook
        // assigns the next position scoped to the project.
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'applies_to' => CodexEntryType::cases(),
        ];
    }

    /**
     * Restrict the attribute to a single entry type.
     */
    public function appliesTo(CodexEntryType $type): static
    {
        return $this->state(['applies_to' => [$type]]);
    }
}
