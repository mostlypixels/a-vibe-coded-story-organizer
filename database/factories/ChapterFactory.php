<?php

namespace Database\Factories;

use App\Models\Act;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // position is intentionally omitted: the Chapter::creating() hook
        // assigns the next position scoped to the parent act.
        return [
            'act_id' => Act::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }
}
