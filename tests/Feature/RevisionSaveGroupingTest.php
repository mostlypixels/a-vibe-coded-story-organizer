<?php

namespace Tests\Feature;

use App\Enums\BookLanguage;
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

/** Group revisions from one entity and request under one save identifier. */
class RevisionSaveGroupingTest extends TestCase
{
    use RefreshDatabase;

    /** Reset scoped services to simulate another request. */
    private function simulateNewRequest(): void
    {
        $this->app->forgetScopedInstances();
    }

    private function hashOf(?string $value): string
    {
        return hash('sha256', $value ?? '');
    }

    /** @return array<int, string> */
    private function saveIdsFor(Project $project, RevisionOrigin $origin): array
    {
        return Revision::query()
            ->where('revisionable_type', Project::class)
            ->where('revisionable_id', $project->id)
            ->where('origin', $origin)
            ->pluck('save_id')
            ->all();
    }

    public function test_one_form_submit_changing_two_autosaved_fields_writes_one_save_point(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create([
            'description' => '<p>Old description</p>',
            'notes' => '<p>Old notes</p>',
        ]);

        $this->actingAs($user)->put(route('scenes.update', $scene), [
            'chapter_id' => $chapter->id,
            'name' => $scene->name,
            'status' => $scene->status->value,
            'contents' => $scene->contents,
            'description' => '<p>New description</p>',
            'notes' => '<p>New notes</p>',
        ])->assertRedirect();

        $manualSaveIds = Revision::query()
            ->where('revisionable_type', Scene::class)
            ->where('revisionable_id', $scene->id)
            ->where('origin', RevisionOrigin::Manual)
            ->pluck('save_id')
            ->all();

        $this->assertCount(2, $manualSaveIds, 'both changed fields should be recorded');

        // The assertion that proves AppServiceProvider's
        // `$this->app->scoped(RevisionRecorder::class)` binding: drop that
        // binding and each field resolves its own recorder, so this collapses
        // to two distinct save ids and the two fields of one Save show up as
        // two unrelated save points in the history.
        $this->assertCount(1, array_unique($manualSaveIds));
    }

    public function test_a_second_form_submit_opens_a_new_save_point(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => '<p>One</p>']);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => BookLanguage::English->value,
            'description' => '<p>Two</p>',
        ])->assertRedirect();

        $this->simulateNewRequest();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => BookLanguage::English->value,
            'description' => '<p>Three</p>',
        ])->assertRedirect();

        $this->assertCount(2, array_unique($this->saveIdsFor($project, RevisionOrigin::Manual)));
    }

    public function test_an_autosave_and_a_later_form_submit_are_different_save_points(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => '<p>One</p>']);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'project', 'id' => $project->id, 'field' => 'description']),
            ['value' => '<p>Two</p>', 'base_hash' => $this->hashOf('<p>One</p>')],
        )->assertOk();

        $this->simulateNewRequest();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => BookLanguage::English->value,
            'description' => '<p>Three</p>',
        ])->assertRedirect();

        $automatic = $this->saveIdsFor($project, RevisionOrigin::Automatic);
        $manual = $this->saveIdsFor($project, RevisionOrigin::Manual);

        $this->assertCount(1, $automatic);
        $this->assertCount(1, $manual);
        $this->assertNotSame($automatic[0], $manual[0]);
    }

    public function test_a_coalescing_autosave_keeps_the_rows_original_save_point_and_timestamp(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create(['contents' => 'original']);
        $recorder = app(RevisionRecorder::class);

        $first = $recorder->record($scene, 'contents', 'first draft', $user, RevisionOrigin::Automatic);
        $originalSaveId = $first->save_id;
        $originalCreatedAt = $first->created_at;

        // Still inside Scene.contents' 60-second window (config/revisions.php),
        // but in what a fresh container would consider a new save.
        $this->travel(30)->seconds();
        $recorder->startNewSave($scene);

        $second = $recorder->record($scene, 'contents', 'second draft', $user, RevisionOrigin::Automatic);

        $this->assertTrue($first->is($second));

        $row = $first->fresh();
        $this->assertSame('second draft', $row->value);
        $this->assertSame($originalSaveId, $row->save_id);
        $this->assertTrue($originalCreatedAt->equalTo($row->created_at));
    }

    public function test_two_entities_written_in_one_request_get_different_save_points(): void
    {
        $user = User::factory()->create();
        $firstAct = Act::factory()->create(['description' => '<p>One</p>']);
        $secondAct = Act::factory()->create(['description' => '<p>Two</p>']);

        // One recorder instance stands in for one request — an import writes
        // revisions for hundreds of entities this way, and they must not all
        // collapse into a single "Undo this save".
        $recorder = app(RevisionRecorder::class);

        $first = $recorder->record($firstAct, 'description', '<p>One edited</p>', $user, RevisionOrigin::Manual);
        $second = $recorder->record($secondAct, 'description', '<p>Two edited</p>', $user, RevisionOrigin::Manual);

        $this->assertNotSame($first->save_id, $second->save_id);
    }

    public function test_a_baseline_row_gets_a_save_point_of_its_own(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->create(['description' => '<p>Pre-existing</p>']);
        $recorder = app(RevisionRecorder::class);

        $written = $recorder->record($act, 'description', '<p>Edited</p>', $user, RevisionOrigin::Manual);

        $baseline = Revision::query()
            ->where('revisionable_type', Act::class)
            ->where('revisionable_id', $act->id)
            ->where('origin', RevisionOrigin::Baseline)
            ->sole();

        $this->assertNotNull($baseline->save_id);
        $this->assertNotSame($written->save_id, $baseline->save_id);
    }

    public function test_no_write_path_ever_leaves_save_id_null(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => '<p>One</p>']);

        // Autosave (plus the baseline it seeds on the way).
        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'project', 'id' => $project->id, 'field' => 'description']),
            ['value' => '<p>Two</p>', 'base_hash' => $this->hashOf('<p>One</p>')],
        )->assertOk();

        // Manual save.
        $this->simulateNewRequest();
        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => BookLanguage::English->value,
            'description' => '<p>Three</p>',
        ])->assertRedirect();

        // Revert.
        $this->simulateNewRequest();
        $target = Revision::query()
            ->where('revisionable_type', Project::class)
            ->where('revisionable_id', $project->id)
            ->where('origin', RevisionOrigin::Baseline)
            ->sole();

        $this->actingAs($user)->post(route('revisions.revert', $target), [
            'base_hash' => $this->hashOf($project->fresh()->description),
        ])->assertRedirect();

        $this->assertGreaterThan(0, Revision::query()->where('origin', RevisionOrigin::Revert)->count());
        $this->assertSame(0, Revision::query()->whereNull('save_id')->count());
    }
}
