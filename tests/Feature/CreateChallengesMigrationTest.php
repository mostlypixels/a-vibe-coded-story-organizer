<?php

namespace Tests\Feature;

use App\Enums\ChallengeRecurrence;
use App\Models\Challenge;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateChallengesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_has_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('challenges'));
        $this->assertTrue(Schema::hasColumns('challenges', [
            'id',
            'project_id',
            'name',
            'recurrence',
            'starts_on',
            'ends_on',
            'target_words',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_ends_on_is_nullable(): void
    {
        $project = Project::factory()->create();

        $challenge = Challenge::query()->create([
            'project_id' => $project->id,
            'name' => 'NaNoWriMo',
            'recurrence' => ChallengeRecurrence::Monthly,
            'starts_on' => '2026-08-01',
            'ends_on' => null,
            'target_words' => 50000,
        ]);

        $this->assertNull($challenge->fresh()->ends_on);
    }

    public function test_the_factory_builds_a_fixed_and_a_monthly_challenge(): void
    {
        $fixed = Challenge::factory()->create();
        $monthly = Challenge::factory()->monthly()->create();

        $this->assertSame(ChallengeRecurrence::None, $fixed->recurrence);
        $this->assertNotNull($fixed->ends_on);

        $this->assertSame(ChallengeRecurrence::Monthly, $monthly->recurrence);
        $this->assertNull($monthly->ends_on);
    }

    public function test_deleting_a_project_cascades_its_challenges(): void
    {
        $project = Project::factory()->create();
        Challenge::factory()->count(3)->create(['project_id' => $project->id]);

        $project->delete();

        $this->assertSame(0, Challenge::query()->count());
    }

    public function test_the_project_relation_orders_challenges_newest_window_first(): void
    {
        $project = Project::factory()->create();
        $older = Challenge::factory()->create(['project_id' => $project->id, 'starts_on' => '2026-06-01']);
        $newer = Challenge::factory()->create(['project_id' => $project->id, 'starts_on' => '2026-08-01']);

        $this->assertSame([$newer->id, $older->id], $project->challenges->pluck('id')->all());
    }
}
