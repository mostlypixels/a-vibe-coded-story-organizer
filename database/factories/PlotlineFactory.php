<?php

namespace Database\Factories;

use App\Models\Plotline;
use App\Models\Project;
use App\Support\PlotlineColors;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plotline>
 */
class PlotlineFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(array_slice(PlotlineColors::PRESETS, 1)),
        ];
    }
}
