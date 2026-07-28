<?php

namespace Tests\Feature;

use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 03 — the schema migration that adds `scenes.word_count` and backfills
 * it for every scene that exists when the migration runs.
 *
 * RefreshDatabase already runs this migration once per test, before any
 * scenes exist — so the column is present with every test starting fresh,
 * but the automatic run backfills nothing. To exercise the backfill itself,
 * each test calls the migration's own down()/up() directly against rows it
 * has just created, exactly as the migration would run against an existing
 * install's data.
 */
class AddWordCountToScenesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_07_28_000000_add_word_count_to_scenes_table.php');

        return $migration;
    }

    public function test_a_scene_created_before_the_migration_gets_the_correct_count_after_it(): void
    {
        $migration = $this->migration();

        // Undo what RefreshDatabase's own migration run already did, so this
        // scene is created against a schema with no word_count column yet —
        // exactly like an existing install before this migration ships.
        $migration->down();

        $scene = Scene::factory()->create(['contents' => 'One two three four.']);

        $migration->up();

        $this->assertSame(4, $scene->fresh()->word_count);
    }

    public function test_a_scene_with_null_or_empty_contents_gets_zero(): void
    {
        $migration = $this->migration();
        $migration->down();

        $sceneWithNullContents = Scene::factory()->create(['contents' => null]);
        $sceneWithBlankContents = Scene::factory()->create(['contents' => "  \n  "]);

        $migration->up();

        $this->assertSame(0, $sceneWithNullContents->fresh()->word_count);
        $this->assertSame(0, $sceneWithBlankContents->fresh()->word_count);
    }

    public function test_the_backfill_writes_no_revision_rows(): void
    {
        $migration = $this->migration();
        $migration->down();

        Scene::factory()->count(3)->create();

        $revisionCountBeforeBackfill = Revision::query()->count();

        $migration->up();

        // The backfill writes through DB::table()->update(), never
        // $scene->save() — a model save fires HasRevisions and would invent
        // a revision row per scene for an edit nobody made.
        $this->assertSame($revisionCountBeforeBackfill, Revision::query()->count());
    }

    public function test_the_backfill_does_not_change_updated_at(): void
    {
        $migration = $this->migration();
        $migration->down();

        $scene = Scene::factory()->create();
        $updatedAtBeforeBackfill = $scene->updated_at;

        $migration->up();

        $this->assertTrue($updatedAtBeforeBackfill->equalTo($scene->fresh()->updated_at));
    }

    public function test_down_then_up_leaves_the_column_and_counts_correct(): void
    {
        $scene = Scene::factory()->create(['contents' => 'One two three.']);

        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('scenes', 'word_count'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('scenes', 'word_count'));
        $this->assertSame(3, $scene->fresh()->word_count);
    }
}
