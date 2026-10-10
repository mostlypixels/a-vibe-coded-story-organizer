<?php

namespace Database\Factories;

use App\Models\NoteCategory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteCategory>
 */
class NoteCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
        ];
    }
}
