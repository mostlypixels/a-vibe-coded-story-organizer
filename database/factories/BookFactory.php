<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // position is intentionally omitted: the Book::creating() hook assigns
        // the next position scoped to the parent project. A project made by
        // ProjectFactory already holds its first book, so a hard-coded 1 here
        // would collide with it.
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
        ];
    }

    /**
     * A book with no name of its own, tracking the project's name — the state
     * every project's first book is created in.
     */
    public function unnamed(): static
    {
        return $this->state(fn (array $attributes): array => ['name' => null]);
    }
}
