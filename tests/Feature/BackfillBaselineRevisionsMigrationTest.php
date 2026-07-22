<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 05 — the data migration that seeds a `baseline` revision for every
 * existing row of every App\Support\AutosavableFields-registered model, via
 * the identical App\Services\RevisionRecorder::ensureBaseline() code path the
 * live autosave write uses (handoff.md §9.2's "identical code path"
 * requirement).
 *
 * RefreshDatabase already runs this migration once per test, before any
 * factory rows exist — so by the time a test seeds rows, the automatic run
 * had nothing to backfill. Every test here re-runs the migration's up()
 * directly (`include` + `->up()`) against rows it has just created, exactly
 * as the migration would be re-run against an existing install's data.
 */
class BackfillBaselineRevisionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfillMigration(): void
    {
        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_07_22_000002_backfill_baseline_revisions.php');

        $migration->up();
    }

    public function test_seeds_a_baseline_for_every_registered_field_with_a_non_empty_value(): void
    {
        $project = Project::factory()->create([
            'description' => 'project description',
            'dedication' => 'project dedication',
            'acknowledgements' => 'project acknowledgements',
            'preface' => 'project preface',
            'postface' => 'project postface',
            'rights' => 'project rights',
        ]);
        $act = Act::factory()->for($project)->create(['description' => 'act description']);
        $chapter = Chapter::factory()->for($act)->create(['description' => 'chapter description']);
        $plotline = Plotline::factory()->for($project)->create(['description' => 'plotline description']);
        $event = Event::factory()->for($project)->create(['description' => 'event description']);
        $scene = Scene::factory()->for($chapter)->create([
            'description' => 'scene description',
            'notes' => 'scene notes',
            'contents' => 'scene contents',
        ]);
        $codexEntry = CodexEntry::factory()->for($project)->create(['description' => 'codex description']);

        $this->runBackfillMigration();

        $expectations = [
            [$project, 'description', 'project description'],
            [$project, 'dedication', 'project dedication'],
            [$project, 'acknowledgements', 'project acknowledgements'],
            [$project, 'preface', 'project preface'],
            [$project, 'postface', 'project postface'],
            [$project, 'rights', 'project rights'],
            [$act, 'description', 'act description'],
            [$chapter, 'description', 'chapter description'],
            [$plotline, 'description', 'plotline description'],
            [$event, 'description', 'event description'],
            [$scene, 'description', 'scene description'],
            [$scene, 'notes', 'scene notes'],
            [$scene, 'contents', 'scene contents'],
            [$codexEntry, 'description', 'codex description'],
        ];

        foreach ($expectations as [$entity, $field, $value]) {
            $entityClass = $entity::class;
            $label = "{$entityClass}.{$field}";

            $baseline = Revision::query()
                ->where('revisionable_type', $entityClass)
                ->where('revisionable_id', $entity->id)
                ->where('field', $field)
                ->sole();

            $this->assertSame(RevisionOrigin::Baseline, $baseline->origin, $label);
            $this->assertSame($value, $baseline->value, $label);
            $this->assertSame(strlen($value), $baseline->size_bytes, $label);
            $this->assertTrue($baseline->created_at->equalTo($entity->updated_at), $label);
        }
    }

    public function test_a_field_left_null_or_empty_gets_no_baseline_row(): void
    {
        $scene = Scene::factory()->create(['notes' => null]);

        $this->runBackfillMigration();

        $this->assertSame(
            0,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'notes')
                ->count(),
        );

        // The scene's other registered fields (description, contents) are
        // non-empty by the factory's default state, and still get seeded —
        // proves the migration skips only the empty field, not the whole row.
        $this->assertSame(
            1,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'description')
                ->count(),
        );
    }

    public function test_running_the_migration_twice_does_not_create_duplicate_baseline_rows(): void
    {
        $project = Project::factory()->create(['rights' => 'project rights']);

        $this->runBackfillMigration();
        $this->runBackfillMigration();

        $this->assertSame(
            1,
            Revision::query()
                ->where('revisionable_type', Project::class)
                ->where('revisionable_id', $project->id)
                ->where('field', 'rights')
                ->count(),
        );
    }

    public function test_a_field_with_a_preexisting_revision_is_skipped_and_never_overwritten(): void
    {
        $scene = Scene::factory()->create(['contents' => 'current contents']);
        $user = User::factory()->create();

        $existing = Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'user_id' => $user->id,
            'field' => 'contents',
            'value' => 'manually saved contents',
            'size_bytes' => strlen('manually saved contents'),
            'origin' => RevisionOrigin::Manual,
            'created_at' => now(),
        ]);

        $this->runBackfillMigration();

        $contentsRevisions = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->get();

        $this->assertCount(1, $contentsRevisions);
        $this->assertTrue($contentsRevisions->first()->is($existing));
        $this->assertSame(RevisionOrigin::Manual, $contentsRevisions->first()->origin);
        $this->assertSame('manually saved contents', $contentsRevisions->first()->value);
    }
}
