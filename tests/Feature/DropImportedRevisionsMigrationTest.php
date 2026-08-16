<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Project;
use App\Models\Revision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The data migration deletes every revision an import replayed, because
 * `RevisionOrigin` no longer has an `import` case and a surviving `'import'`
 * row throws when the model hydrates it.
 *
 * RefreshDatabase runs the migration before any factory row exists, so each
 * test re-runs `up()` directly against rows it just made — the same thing the
 * migration meets on an existing install.
 *
 * The rows go in through the query builder: the enum case is gone, so the
 * model can no longer make one.
 */
class DropImportedRevisionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runDropMigration(): void
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_08_16_000000_drop_imported_revisions.php');

        $migration->up();
    }

    public function test_it_deletes_the_imported_rows_and_keeps_every_other_origin(): void
    {
        $project = Project::factory()->create();

        $survivors = [];

        foreach ([RevisionOrigin::Manual, RevisionOrigin::Automatic, RevisionOrigin::Baseline, RevisionOrigin::Revert] as $index => $origin) {
            $survivors[] = Revision::factory()->create([
                'revisionable_type' => Project::class,
                'revisionable_id' => $project->id,
                'project_id' => $project->id,
                'field' => "field_{$index}",
                'origin' => $origin,
            ]);
        }

        $importedId = DB::table('revisions')->insertGetId([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'field' => 'preface',
            'value' => 'replayed from an archive',
            'size_bytes' => strlen('replayed from an archive'),
            'origin' => 'import',
            'label' => null,
            'save_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        $this->runDropMigration();

        $this->assertDatabaseMissing('revisions', ['id' => $importedId]);

        foreach ($survivors as $survivor) {
            $this->assertModelExists($survivor);
        }
    }

    public function test_it_is_safe_to_run_when_no_imported_row_exists(): void
    {
        $revision = Revision::factory()->create(['origin' => RevisionOrigin::Manual]);

        $this->runDropMigration();

        $this->assertModelExists($revision);
        $this->assertDatabaseCount('revisions', 1);
    }
}
