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
use App\Support\FieldHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * App\Services\RevisionRecorder: coalescing writes and baseline seeding. These
 * tests call it directly. FieldAutosaveTest covers the controller path, and the
 * backfill migration reuses the same ensureBaseline() code.
 */
class RevisionRecorderTest extends TestCase
{
    use RefreshDatabase;

    private RevisionRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = app(RevisionRecorder::class);
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
            'contents' => 'Old contents',
        ]);
        $user = User::factory()->create();

        $before = [
            'description' => 'Same description',
            'contents' => 'Old contents',
        ];

        // Simulates the caller having already applied the form's new values to the
        // model (App\Support\AutosavableFields::snapshotFieldsBeforeUpdate()'s
        // contract) — only 'contents' actually differs from $before.
        $scene->contents = 'New contents';

        $this->recorder->recordManualChanges($scene, $before, $user, 'Saved 24 July 10:43');

        $this->assertSame(0, $scene->revisions()->where('field', 'description')->count());

        $contentsRevision = $scene->revisions()->where('field', 'contents')->latest('created_at')->latest('id')->first();
        $this->assertNotNull($contentsRevision);
        $this->assertSame(RevisionOrigin::Manual, $contentsRevision->origin);
        $this->assertSame('New contents', $contentsRevision->value);
        $this->assertSame('Saved 24 July 10:43', $contentsRevision->label);
    }

    public function test_save_with_manual_checkpoint_records_the_change_that_the_callback_saves(): void
    {
        $scene = Scene::factory()->create(['contents' => 'Old contents', 'description' => 'Same description']);
        $user = User::factory()->create();
        $data = ['contents' => 'New contents', 'description' => 'Same description'];

        $result = $this->recorder->saveWithManualCheckpoint($scene, $data, [], $user, fn () => $scene->update($data));

        $this->assertTrue($result);
        $this->assertSame(0, $scene->revisions()->where('field', 'description')->count());
        $this->assertSame(
            ['Old contents', 'New contents'],
            $scene->revisions()->where('field', 'contents')->reorder('id')->pluck('value')->all(),
        );
    }

    /** An autosave from another tab can land after the form request validated the hashes. */
    public function test_save_with_manual_checkpoint_rejects_text_saved_after_validation(): void
    {
        $scene = Scene::factory()->create(['description' => 'Old description']);
        $staleScene = Scene::findOrFail($scene->id);
        $user = User::factory()->create();
        $scene->update(['description' => 'Autosaved elsewhere']);
        $data = ['description' => 'Form description'];

        try {
            $this->recorder->saveWithManualCheckpoint($staleScene, $data, ['description' => FieldHash::of('Old description')], $user, fn () => $staleScene->update($data));
            $this->fail('A save over newer text must throw.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('base_hashes.description', $exception->errors());
        }

        $this->assertSame('Autosaved elsewhere', $scene->fresh()->description);
        $this->assertSame(0, $scene->revisions()->count());
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
            ->reorder('id')
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
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'original']);
        $user = User::factory()->create();

        $revision = $this->recorder->record($scene, 'contents', 'edited', $user, RevisionOrigin::Automatic);

        $this->assertSame($project->id, $revision->project_id);
    }

    public function test_record_sets_project_id_to_its_own_id_for_a_project_entity(): void
    {
        $project = Project::factory()->create(['description' => 'original']);
        $user = User::factory()->create();

        $revision = $this->recorder->record($project, 'description', 'edited', $user, RevisionOrigin::Automatic);

        $this->assertSame($project->id, $revision->project_id);
    }

    // ---------------------------------------------------------------------
    // ensureBaseline()
    // ---------------------------------------------------------------------

    public function test_ensure_baseline_seeds_a_baseline_row_from_the_current_value(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
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
        $scene = Scene::factory()->create(['description' => null]);

        $this->recorder->ensureBaseline($scene, 'description');

        $this->assertSame(
            0,
            Revision::query()
                ->where('revisionable_type', Scene::class)
                ->where('revisionable_id', $scene->id)
                ->where('field', 'description')
                ->count(),
        );
    }

    // ---------------------------------------------------------------------
    // Same-second ties: the higher ID is the newer row
    // ---------------------------------------------------------------------

    public function test_the_revisions_relation_puts_the_higher_id_first_in_a_same_second_tie(): void
    {
        $this->freezeTime();
        $scene = Scene::factory()->create();

        // The older row has the field that sorts first, so a timestamp-only order returns it first.
        $older = $this->sceneRevision($scene, 'contents');
        $newer = $this->sceneRevision($scene, 'description');

        $this->assertTrue($scene->revisions()->first()->is($newer));
        $this->assertSame([$newer->id, $older->id], $scene->revisions()->pluck('id')->all());
    }

    public function test_last_revision_for_returns_the_higher_id_in_a_same_second_tie(): void
    {
        $this->freezeTime();
        $scene = Scene::factory()->create();

        $this->sceneRevision($scene, 'contents');
        $newer = $this->sceneRevision($scene, 'contents');

        $this->assertTrue($this->recorder->lastRevisionFor($scene, 'contents')->is($newer));
    }

    public function test_record_coalesces_into_the_higher_id_in_a_same_second_tie(): void
    {
        $this->freezeTime();
        $scene = Scene::factory()->create();
        $user = User::factory()->create();

        $this->sceneRevision($scene, 'contents');
        $newer = $this->sceneRevision($scene, 'contents');

        $revision = $this->recorder->record($scene, 'contents', 'next draft', $user, RevisionOrigin::Automatic);

        $this->assertTrue($revision->is($newer));
    }

    private function sceneRevision(Scene $scene, string $field): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->revisionProject()->id,
            'field' => $field,
            'origin' => RevisionOrigin::Automatic,
        ]);
    }
}
