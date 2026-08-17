<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\RevisionRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that adds the save-point grouping key and the pre-computed
 * summary columns to `revisions` — and, on the way, deletes every row that
 * predates the new write path.
 *
 * RefreshDatabase has already run this migration by the time a test body
 * starts, so a test cannot simply call up() again (the columns would already
 * exist). Instead each test that needs the "before" state calls down() first,
 * which puts the table back in its pre-save-point shape with its rows intact —
 * exactly the state a real install is in when this migration runs.
 */
class AddSaveGroupingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_COLUMNS = ['save_id', 'summary_html', 'change_count'];

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_07_25_000000_add_save_grouping_to_revisions_table.php');

        return $migration;
    }

    public function test_rows_that_existed_before_the_migration_are_deleted_by_it(): void
    {
        Revision::factory()->count(3)->create();

        $migration = $this->migration();
        $migration->down();

        // The rows survive the rollback — it only drops columns — so up() is
        // running against a table that still holds legacy history, just like a
        // real install's would.
        $this->assertSame(3, DB::table('revisions')->count());

        $migration->up();

        $this->assertSame(0, DB::table('revisions')->count());
    }

    public function test_the_three_new_columns_exist_and_are_fillable(): void
    {
        foreach (self::NEW_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('revisions', $column), $column);
        }

        $revision = Revision::factory()->create([
            'save_id' => '01J0000000000000000000000A',
            'summary_html' => '<ins>added</ins>',
            'change_count' => 2,
        ]);

        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id,
            'save_id' => '01J0000000000000000000000A',
            'summary_html' => '<ins>added</ins>',
            'change_count' => 2,
        ]);
    }

    public function test_down_drops_the_columns_and_up_can_run_again_afterwards(): void
    {
        $migration = $this->migration();

        $migration->down();

        foreach (self::NEW_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('revisions', $column), $column);
        }

        $migration->up();

        foreach (self::NEW_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('revisions', $column), $column);
        }

        // The indexes came back too, which is what makes a second up() a real
        // re-run rather than a half-applied one: adding an index that already
        // exists would have thrown.
        $this->assertNotNull(Revision::factory()->create()->save_id);
    }

    public function test_the_first_write_after_the_migration_reseeds_a_baseline_from_the_live_value(): void
    {
        $scene = Scene::factory()->create(['contents' => 'the value before revisions were cleared']);
        $user = User::factory()->create();

        Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->book->project->id,
            'field' => 'contents',
            'value' => 'legacy history',
        ]);

        $migration = $this->migration();
        $migration->down();
        $migration->up();

        $this->assertSame(0, DB::table('revisions')->count());

        app(RevisionRecorder::class)->record($scene, 'contents', 'freshly typed contents', $user, RevisionOrigin::Automatic);

        $baseline = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->where('origin', RevisionOrigin::Baseline)
            ->sole();

        $this->assertSame('the value before revisions were cleared', $baseline->value);
    }

    public function test_the_factory_gives_every_row_its_own_non_null_save_id(): void
    {
        $saveIds = Revision::factory()->count(3)->create()->pluck('save_id');

        $this->assertCount(3, $saveIds->filter());
        $this->assertCount(3, $saveIds->unique());
        $this->assertSame([26], $saveIds->map(fn (string $saveId) => strlen($saveId))->unique()->values()->all());
    }
}
