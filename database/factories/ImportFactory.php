<?php

namespace Database\Factories;

use App\Enums\ImportPhase;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state: a freshly-uploaded, not-yet-started
     * import (no project, no id maps).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'archive_path' => 'imports/'.fake()->uuid().'.zip',
            'archive_original_name' => fake()->slug().'.zip',
            'phase' => ImportPhase::Pending,
            'id_maps' => null,
            'queued' => false,
            'failure_message' => null,
        ];
    }

    /**
     * Place the import at a given phase. Convenience for tests that assert a
     * valid row exists at each ImportPhase.
     */
    public function phase(ImportPhase $phase): static
    {
        return $this->state(fn () => ['phase' => $phase]);
    }
}
