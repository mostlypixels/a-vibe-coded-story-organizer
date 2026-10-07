<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser tab's <title>: "<page> - <project> - <app>" inside a project, the
 * bare app name (or "<page> - <app>") outside one. Titles come from App\Support\PageTitle, fed by the same
 * project resolution the navigation uses — including the shallow child routes
 * (/scenes/{scene}/edit) that carry no {project} parameter.
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_outside_a_project_lead_with_their_own_title(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))
            ->assertSee('<title>Welcome - AVCSO</title>', false);
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee('<title>Profile - AVCSO</title>', false);
    }

    public function test_an_admin_page_passes_its_title_through_the_admin_layout(): void
    {
        config(['app.name' => 'AVCSO']);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'));

        $response->assertSee('<title>General settings - AVCSO</title>', false);
    }

    public function test_a_revision_history_page_passes_its_heading_through_the_revisions_layout(): void
    {
        config(['app.name' => 'AVCSO']);
        [$user, $scene] = $this->sceneInProject('Melusine', 'The ambush');

        $response = $this->actingAs($user)->get(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id]));

        $response->assertSee('<title>Scene &quot;The ambush&quot; — History - AVCSO</title>', false);
    }

    public function test_a_project_page_without_a_trail_keeps_the_project_name_after_its_title(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->get(route('projects.challenges.create', $project));

        $response->assertSee('<title>New Challenge - Melusine - AVCSO</title>', false);
    }

    public function test_a_project_page_leads_with_the_project_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
    }

    public function test_the_title_ignores_the_stored_active_project(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $user->forceFill(['active_project_id' => $project->id])->save();

        $response = $this->actingAs($user)->get(route('projects.index'));

        // The nav falls back to the account's active project off-route; the title
        // deliberately does not. Building it from $navigation->project instead of
        // ->routeProject retitles the dashboard "Melusine - AVCSO" and makes it
        // indistinguishable from the project's own tab. This is that regression.
        $response->assertSee('<title>Projects - AVCSO</title>', false);
    }

    public function test_a_book_page_leads_with_the_books_own_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $response = $this->actingAs($user)->get(route('books.story.overview', $book));

        $response->assertSee('<title>Overview - Volume One - AVCSO</title>', false);
    }

    public function test_a_sole_unnamed_book_renders_exactly_the_project_title(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        $response = $this->actingAs($user)->get(route('books.story.overview', $book));

        $response->assertSee('<title>Overview - Melusine - AVCSO</title>', false);
    }

    public function test_a_project_scoped_page_ignores_a_named_book_and_uses_the_project_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        // The timeline route carries no {book} — the title must not leak the
        // route's book in from anywhere else (e.g. the stored last_book_id).
        $response = $this->actingAs($user)->get(route('projects.timeline.home', $project));

        $response->assertSee('<title>Timeline - Melusine - AVCSO</title>', false);
    }

    public function test_a_shallow_child_route_still_finds_its_project(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'The ambush']);

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertSee('<title>The ambush - Edit - Melusine - AVCSO</title>', false);
    }

    public function test_a_scene_page_and_its_edit_page_differ_and_both_name_the_scene(): void
    {
        config(['app.name' => 'AVCSO']);
        [$user, $scene] = $this->sceneInProject('Melusine', 'The ambush');

        $this->actingAs($user)->get(route('scenes.show', $scene))
            ->assertSee('<title>The ambush - Melusine - AVCSO</title>', false);
        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertSee('<title>The ambush - Edit - Melusine - AVCSO</title>', false);
    }

    public function test_an_index_page_leads_with_its_breadcrumb(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        $this->actingAs($user)->get(route('books.scenes.index', $book))
            ->assertSee('<title>Scenes - Melusine - AVCSO</title>', false);
        $this->actingAs($user)->get(route('books.scenes.create', $book))
            ->assertSee('<title>New scene - Melusine - AVCSO</title>', false);
    }

    public function test_the_project_settings_page_does_not_repeat_the_project_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertSee('<title>Edit - Melusine - AVCSO</title>', false);
    }

    public function test_a_page_without_a_trail_can_give_its_own_title(): void
    {
        config(['app.name' => 'AVCSO']);

        $response = $this->actingAs(User::factory()->create())->get(route('account'));

        $response->assertSee('<title>Account - AVCSO</title>', false);
    }

    /**
     * @return array{User, Scene}
     */
    private function sceneInProject(string $projectName, string $sceneName): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => $projectName]);
        $act = Act::factory()->for($project->books()->first())->create();
        $chapter = Chapter::factory()->for($act)->create();

        return [$user, Scene::factory()->for($chapter)->create(['name' => $sceneName])];
    }
}
