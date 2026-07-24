<?php

namespace Tests\Unit\Services;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\ProjectRevisionsBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Services\ProjectRevisionsBrowser — the tree behind the Tools ▸ Revisions
 * sidebar: every entity/field in a project that has revision history, grouped
 * by type, with per-field counts. Only revised entities/fields appear.
 */
class ProjectRevisionsBrowserTest extends TestCase
{
    use RefreshDatabase;

    private ProjectRevisionsBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = new ProjectRevisionsBrowser;
    }

    private function revisionFor(string $type, int $id, int $projectId, string $field, int $count): void
    {
        Revision::factory()->count($count)->create([
            'revisionable_type' => $type,
            'revisionable_id' => $id,
            'project_id' => $projectId,
            'field' => $field,
        ]);
    }

    public function test_tree_groups_revised_entities_by_type_with_per_field_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);

        // A project-level field, an act, and a scene (reached through
        // chapter → act → project) all with history.
        $act = Act::factory()->for($project)->create(['name' => 'Act Alpha']);
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Scene One']);

        $this->revisionFor(Project::class, $project->id, $project->id, 'description', 1);
        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 2);
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'description', 1);
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'notes', 3);

        $tree = $this->browser->tree($project);

        // Groups appear in the declared order, and only the ones with revisions.
        $this->assertSame(['Project', 'Acts', 'Scenes'], $tree->pluck('label')->all());

        // The Scene lists Description before Notes (registry field order), with
        // the right counts; its never-revised `contents` field is absent.
        $sceneGroup = $tree->firstWhere('type', 'scene');
        $sceneEntity = $sceneGroup->entities->firstWhere('id', $scene->id);
        $this->assertSame(['Description', 'Notes'], $sceneEntity->fields->pluck('label')->all());
        $this->assertSame([1, 3], $sceneEntity->fields->pluck('count')->all());
    }

    public function test_entities_without_revisions_are_absent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $revised = Act::factory()->for($project)->create(['name' => 'Revised Act']);
        Act::factory()->for($project)->create(['name' => 'Untouched Act']);

        $this->revisionFor(Act::class, $revised->id, $project->id, 'description', 1);

        $tree = $this->browser->tree($project);

        $actEntities = $tree->firstWhere('type', 'act')->entities;
        $this->assertSame(['Revised Act'], $actEntities->pluck('name')->all());
    }

    public function test_a_field_leaf_links_to_its_history_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 1);

        $leaf = $this->browser->tree($project)
            ->firstWhere('type', 'act')
            ->entities->first()
            ->fields->first();

        $this->assertSame(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            $leaf->url,
        );
    }

    public function test_an_empty_project_yields_an_empty_tree(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->assertTrue($this->browser->tree($project)->isEmpty());
    }

    public function test_the_tree_never_hydrates_the_revision_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 1);

        DB::enableQueryLog();
        $this->browser->tree($project);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The list-query invariant: the browser only needs coordinates + counts,
        // so the heavy `value` column must never be selected by any of its queries.
        foreach ($queries as $query) {
            $this->assertStringNotContainsStringIgnoringCase('value', $query['query']);
        }
    }
}
