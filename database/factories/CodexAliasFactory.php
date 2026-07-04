<?php

namespace Database\Factories;

use App\Models\CodexAlias;
use App\Models\CodexEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodexAlias>
 */
class CodexAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codex_entry_id' => CodexEntry::factory(),
            'alias' => fake()->name(),
        ];
    }
}
