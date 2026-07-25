<?php

namespace Database\Factories;

use App\Enums\RevisionOrigin;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Revision>
 */
class RevisionFactory extends Factory
{
    protected $model = Revision::class;

    /**
     * Define the model's default state.
     *
     * Defaults to revisioning a Project's own "description" field so a bare
     * `Revision::factory()->create()` needs no relation set up by the caller;
     * tests that care about a specific (revisionable_type, revisionable_id,
     * field) triple override those attributes explicitly.
     *
     * Every created row gets its own fresh `save_id`, so factory-made rows look
     * like real ones (the column is never null in production) and no test has
     * to remember to set it. Tests that need several rows in *one* save point
     * pass the same `save_id` to each explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory()->create();
        $value = fake()->paragraph();

        return [
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            'value' => $value,
            'size_bytes' => strlen($value),
            'project_id' => $project->id,
            'user_id' => User::factory(),
            'label' => null,
            'origin' => RevisionOrigin::Automatic,
            'created_at' => now(),
        ];
    }
}
