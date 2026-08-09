<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WordCountSnapshot>
 */
class WordCountSnapshotFactory extends Factory
{
    protected $model = WordCountSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'recorded_on' => fake()->date(),
            'word_count' => fake()->numberBetween(0, 50000),
        ];
    }
}
