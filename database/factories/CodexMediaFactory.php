<?php

namespace Database\Factories;

use App\Enums\CodexMediaCollection;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodexMedia>
 */
class CodexMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // position is intentionally omitted: the CodexMedia::creating() hook
        // assigns the next position scoped to (entry, collection).
        $originalName = fake()->slug().'.jpg';

        return [
            'codex_entry_id' => CodexEntry::factory(),
            'collection' => CodexMediaCollection::ReferenceImage,
            'path' => 'codex-media/'.fake()->uuid().'.jpg',
            'original_name' => $originalName,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1_000, 5_000_000),
        ];
    }

    public function cover(): static
    {
        return $this->state(['collection' => CodexMediaCollection::Cover]);
    }

    public function referenceImage(): static
    {
        return $this->state(['collection' => CodexMediaCollection::ReferenceImage]);
    }

    public function referenceFile(): static
    {
        return $this->state([
            'collection' => CodexMediaCollection::ReferenceFile,
            'path' => 'codex-media/'.fake()->uuid().'.pdf',
            'original_name' => fake()->slug().'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}
