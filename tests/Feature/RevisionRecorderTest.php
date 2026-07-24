<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\RevisionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 04 — App\Services\RevisionRecorder: coalescing writes and baseline
 * seeding. Called directly here (not yet by a controller — that's task 6), and
 * later reused verbatim by the backfill migration (task 5).
 */
class RevisionRecorderTest extends TestCase
{
    use RefreshDatabase;

    private RevisionRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new RevisionRecorder;
    }

    // ---------------------------------------------------------------------
    // record() — coalescing
    // ---------------------------------------------------------------------

    public function test_two_automatic_records_within_the_window_coalesce_into_one_row(): void
    {
        $scene = Scene::factory()->create(['contents' => 'original']);
        $user = User::factory()->create();

        $first = $this->recorder->record($scene, 'contents', 'first draft', $user, RevisionOrigin::Automatic);
        $second = $this->recorder->record($scene, 'contents', 'second draft', $user, RevisionOrigin::Automatic);

        $this->assertTrue($first->is($second));

        $automaticRevisions = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->where('origin', RevisionOrigin::Automatic)
            ->get();

        $this->assertCount(1, $automaticRevisions);
        $this->assertSame('second draft', $automaticRevisions->first()->value);
        $this->assertSame(strlen('second draft'), $automaticRevisions->first()->size_bytes);
    }

    public function test_two_automatic_records_after_the_window_closes_produce_two_rows(): void
    {
        $scene = Scene::factory()->create(['contents' => 'original']);
        $user = User::factory()->create();

        // Scene.contents' coalescing window is 60 seconds (config/revisions.php).
        $this->recorder->record($scene, 'contents', 'first draft', $user, RevisionOrigin::Automatic);

        $this->travel(61)->seconds();

        $this->recorder->record($scene, 'contents', 'second draft', $user, RevisionOrigin::Automatic);

        $automaticRevisions = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->where('origin', RevisionOrigin::Automatic)
            ->get();

        $this->assertCount(2, $automaticRevisions);
    }

    public function test_two_manual_records_in_immediate_succession_never_coalesce(): void
    {
        $scene = Scene::factory()->create(['contents' => 'original']);
        $user = User::factory()->create();

        $this->recorder->record($scene, 'contents', 'first draft', $user, RevisionOrigin::Manual);
        $this->recorder->record($scene, 'contents', 'second draft', $user, RevisionOrigin::Manual);

        $manualRevisions = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->where('origin', RevisionOrigin::Manual)
            ->get();

        $this->assertCount(2, $manualRevisions);
        $this->assertSame(['first draft', 'second draft'], $manualRevisions->pluck('value')->all());
    }

    // ---------------------------------------------------------------------
    // recordManualChanges() — full-form Save checkpoint
    // ---------------------------------------------------------------------

    public function test_record_manual_changes_records_only_the_fields_that_actually_changed(): void
    {
        $scene = Scene::factory()->create([
            'description' => 'Same description',
            'notes' => 'Old notes',
            'contents' => 'Same contents',
        ]);
        $user = User::factory()->create();

        $before = [
            'description' => 'Same description',
            'notes' => 'Old notes',
            'contents' => 'Same contents',
        ];

        // Simulates the caller having already applied the form's new values to the
        // model (App\Support\AutosavableFields::snapshotFieldsBeforeUpdate()'s
        // contract) — only 'notes' actually differs from $before.
        $scene->notes = 'New notes';

        $this->recorder->recordManualChanges($scene, $before, $user, 'Saved 24 July 10:43');

        $this->assertSame(0, $scene->revisions()->where('field', 'description')->count());
        $this->assertSame(0, $scene->revisions()->where('field', 'contents')->count());

        $notesRevision = $scene->revisions()->where('field', 'notes')->latest('created_at')->first();
        $this->assertNotNull($notesRevision);
        $this->assertSame(RevisionOrigin::Manual, $notesRevision->origin);
        $this->assertSame('New notes', $notesRevision->value);
        $this->assertSame('Saved 24 July 10:43', $notesRevision->label);
    }

    public function test_record_manual_changes_always_inserts_a_fresh_row_even_immediately_after_an_automatic_one(): void
    {
        $scene = Scene::factory()->create(['contents' => 'original']);
        $user = User::factory()->create();

        $this->recorder->record($scene, 'contents', 'autosaved draft', $user, RevisionOrigin::Automatic);

        $scene->contents = 'saved via button';
        $this->recorder->recordManualChanges($scene, ['contents' => 'autosaved draft'], $user, 'Saved 24 July 10:43');

        $revisions = $scene->revisions()
            ->where('field', 'contents')
            ->whereIn('origin', [RevisionOrigin::Automatic, RevisionOrigin::Manual])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $revisions);
        $this->assertSame(RevisionOrigin::Automatic, $revisions[0]->origin);
        $this->assertSame(RevisionOrigin::Manual, $revisions[1]->origin);
        $this->assertSame('saved via button', $revisions[1]->value);
    }

    // ---------------------------------------------------------------------
    // record() — project_id resolution
    // ---------------------------------------------------------------------

    public function test_record_sets_project_id_by_walking_a_scene_up_to_its_project(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'original']);
        $user = User::factory()->create();

        $revision = $this->recorder->record($scene, 'contents', 'edited', $user, RevisionOrigin::Automatic);

        $this->assertSame($project->id, $revision->project_id);
    }

    public function test_record_sets_project_id_to_its_own_id_for_a_project_entity(): void
    {
        $project = Project::factory()->create(['rights' => 'original']);
        $user = User::factory()->create();

        $revision = $this->recorder->record($project, 'rights', 'edited', $user, RevisionOrigin::Automatic);

        $this->assertSame($project->id, $revision->project_id);
    }

    // ---------------------------------------------------------------------
    // ensureBaseline()
    // ---------------------------------------------------------------------

    public function test_ensure_baseline_seeds_a_baseline_row_from_the_current_value(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'pre-edit value']);

        $this->recorder->ensureBaseline($scene, 'contents');

        $baseline = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('field', 'contents')
            ->sole();

        $this->assertSame(RevisionOrigin::Baseline, $baseline->origin);
        $this->assertSame('pre-edit value', $baseline->value);
        $this->assertSame(strlen('pre-edit value'), $baseline->size_bytes);
        $this->assertTrue($baseline->created_at->equalTo($scene->updated_at));
        $this->assertSame($project->user_id, $baseline->user_id);
        $this->assertSame($project->id, $baseline->project_id);
    }

    public function test_ensure_baseline_is_idempotent(): void
    {
        $scene = Scene::factory()->create(['contents' => 'pre-edit value']);

        $this->recorder->ensureBaseline($scene, 'contents');
        $this->recorder->ensureBaseline($scene, 'contents');

        $this->assertSame(
            1,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'contents')
                ->count(),
        );
    }

    public function test_ensure_baseline_does_nothing_when_the_current_value_is_empty(): void
    {
        $scene = Scene::factory()->create(['contents' => '']);

        $this->recorder->ensureBaseline($scene, 'contents');

        $this->assertSame(
            0,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'contents')
                ->count(),
        );
    }

    public function test_ensure_baseline_does_nothing_when_the_current_value_is_null(): void
    {
        $scene = Scene::factory()->create(['notes' => null]);

        $this->recorder->ensureBaseline($scene, 'notes');

        $this->assertSame(
            0,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'notes')
                ->count(),
        );
    }
}
