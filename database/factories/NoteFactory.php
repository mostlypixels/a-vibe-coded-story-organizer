<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'note_category_id' => null,
            'title' => fake()->words(3, true),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
