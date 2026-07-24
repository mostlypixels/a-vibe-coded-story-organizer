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
