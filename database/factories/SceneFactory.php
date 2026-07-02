<?php

namespace Database\Factories;

use App\Enums\SceneStatus;
use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // position is intentionally omitted: the Scene::creating() hook assigns
        // the next position scoped to the parent chapter.
        return [
            'chapter_id' => Chapter::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'contents' => fake()->paragraph(),
            'notes' => null,
            'status' => SceneStatus::Draft,
        ];
    }
}
