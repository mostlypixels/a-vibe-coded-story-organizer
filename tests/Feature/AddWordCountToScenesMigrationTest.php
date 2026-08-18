<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Run the word-count migration against rows that predate its column. */
class AddWordCountToScenesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_07_28_000000_add_word_count_to_scenes_table.php');

        return $migration;
    }

    /** Insert a scene without the model's word-count hook. */
    private function insertScene(?string $contents): Scene
    {
        $chapter = Chapter::factory()->create();

        $id = DB::table('scenes')->insertGetId([
            'chapter_id' => $chapter->id,
            'name' => 'Fixture scene',
            'contents' => $contents,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Scene::findOrFail($id);
    }

    public function test_a_scene_created_before_the_migration_gets_the_correct_count_after_it(): void
    {
        $migration = $this->migration();

        // Undo what RefreshDatabase's own migration run already did, so this
        // scene is created against a schema with no word_count column yet —
        // exactly like an existing install before this migration ships.
        $migration->down();

        $scene = $this->insertScene('One two three four.');

        $migration->up();

        $this->assertSame(4, $scene->fresh()->word_count);
    }

    public function test_a_scene_with_null_or_empty_contents_gets_zero(): void
    {
        $migration = $this->migration();
        $migration->down();

        $sceneWithNullContents = $this->insertScene(null);
        $sceneWithBlankContents = $this->insertScene("  \n  ");

        $migration->up();

        $this->assertSame(0, $sceneWithNullContents->fresh()->word_count);
        $this->assertSame(0, $sceneWithBlankContents->fresh()->word_count);
    }

    public function test_the_backfill_writes_no_revision_rows(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->insertScene('One.');
        $this->insertScene('Two.');
        $this->insertScene('Three.');

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

        $scene = $this->insertScene('Some words.');
        $updatedAtBeforeBackfill = $scene->updated_at;

        $migration->up();

        $this->assertTrue($updatedAtBeforeBackfill->equalTo($scene->fresh()->updated_at));
    }

    public function test_down_then_up_leaves_the_column_and_counts_correct(): void
    {
        // Created while the column exists, through the model as normal — this
        // test is about the column surviving a down()/up() cycle, not about
        // reproducing pre-feature data.
        $scene = Scene::factory()->create(['contents' => 'One two three.']);

        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('scenes', 'word_count'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('scenes', 'word_count'));
        $this->assertSame(3, $scene->fresh()->word_count);
    }
}
