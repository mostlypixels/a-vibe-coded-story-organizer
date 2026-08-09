<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateWordCountSnapshotsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_has_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('word_count_snapshots'));
        $this->assertTrue(Schema::hasColumns('word_count_snapshots', [
            'id',
            'project_id',
            'recorded_on',
            'word_count',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_word_count_defaults_to_zero(): void
    {
        $project = Project::factory()->create();

        $snapshot = WordCountSnapshot::query()->create([
            'project_id' => $project->id,
            'recorded_on' => '2026-08-09',
        ]);

        $this->assertSame(0, $snapshot->fresh()->word_count);
    }

    public function test_a_second_row_for_the_same_project_and_day_is_rejected(): void
    {
        $project = Project::factory()->create();
        WordCountSnapshot::factory()->create([
            'project_id' => $project->id,
            'recorded_on' => '2026-08-09',
        ]);

        $this->expectException(QueryException::class);

        WordCountSnapshot::factory()->create([
            'project_id' => $project->id,
            'recorded_on' => '2026-08-09',
        ]);
    }

    public function test_the_same_day_is_allowed_for_a_different_project(): void
    {
        $first = Project::factory()->create();
        $second = Project::factory()->create();

        WordCountSnapshot::factory()->create([
            'project_id' => $first->id,
            'recorded_on' => '2026-08-09',
        ]);
        WordCountSnapshot::factory()->create([
            'project_id' => $second->id,
            'recorded_on' => '2026-08-09',
        ]);

        $this->assertSame(2, WordCountSnapshot::query()->count());
    }

    public function test_deleting_a_project_cascades_its_snapshots(): void
    {
        $project = Project::factory()->create();
        WordCountSnapshot::factory()->count(3)->create(['project_id' => $project->id]);

        $project->delete();

        $this->assertSame(0, WordCountSnapshot::query()->count());
    }
}
