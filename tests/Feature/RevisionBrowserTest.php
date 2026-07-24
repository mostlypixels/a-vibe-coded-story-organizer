<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tools ▸ Revisions — the project-scoped revisions browser
 * (RevisionBrowserController) and its shared sidebar shell. The sidebar lists
 * only entities and fields that actually have revision history.
 */
class RevisionBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function revisionFor(string $type, int $id, int $projectId, string $field): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => $type,
            'revisionable_id' => $id,
            'project_id' => $projectId,
            'field' => $field,
        ]);
    }

    public function test_owner_sees_a_sidebar_listing_a_revised_entitys_field(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['name' => 'Act Alpha']);

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('Act Alpha');
        $response->assertSee(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            false,
        );
    }

    public function test_entities_without_revisions_are_absent_from_the_sidebar(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $revised = Act::factory()->for($project)->create(['name' => 'Revised Act']);
        Act::factory()->for($project)->create(['name' => 'Untouched Act']);

        $this->revisionFor(Act::class, $revised->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('Revised Act');
        $response->assertDontSee('Untouched Act');
    }

    public function test_a_field_without_revisions_is_absent_under_a_revised_entity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Scene One']);

        // Only `notes` has history — `description` and `contents` must not appear.
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'notes');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee(
            route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'notes']),
            false,
        );
        $response->assertDontSee(
            route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'description']),
            false,
        );
    }

    public function test_an_empty_project_shows_the_empty_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('No revisions recorded in this project yet.');
    }

    public function test_a_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.revisions.index', $project))
            ->assertForbidden();
    }

    public function test_the_tools_revisions_link_appears_on_a_project_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(route('projects.revisions.index', $project), false);
    }

    public function test_the_sidebar_offers_a_client_side_filter_box(): void
    {
        // handoff D2: a client-side filter narrows a large sidebar by name.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('x-model="filter"', false);
    }

    public function test_only_the_active_entitys_group_starts_open(): void
    {
        // handoff D2: groups default-collapse to bound a big sidebar; only the
        // group holding the entity currently being viewed starts open.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'notes');

        // Viewing the Act's history: its group opens, the Scene group stays collapsed.
        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        $response->assertSee('open: true', false);
        $response->assertSee('open: false', false);
    }

    public function test_the_history_page_renders_the_browser_sidebar_with_the_active_field(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description'])
        );

        $response->assertOk();
        // Folded into <x-revisions-layout>: the sidebar's "back to project" link
        // and the active-field marker are both present.
        $response->assertSee(route('projects.show', $project), false);
        $response->assertSee('aria-current="page"', false);
    }
}
