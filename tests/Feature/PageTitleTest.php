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
 * The browser tab's <title>: "<project> - <app>" inside a project, the bare app
 * name outside one. Titles come from App\Support\PageTitle, fed by the same
 * project resolution the navigation uses — including the shallow child routes
 * (/scenes/{scene}/edit) that carry no {project} parameter.
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_outside_a_project_show_the_app_name_alone(): void
    {
        config(['app.name' => 'AVCSO']);

        $response = $this->actingAs(User::factory()->create())->get(route('onboarding'));

        $response->assertSee('<title>AVCSO</title>', false);
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
        $response->assertSee('<title>AVCSO</title>', false);
    }

    public function test_a_book_page_leads_with_the_books_own_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $response = $this->actingAs($user)->get(route('books.story.overview', $book));

        $response->assertSee('<title>Volume One - AVCSO</title>', false);
    }

    public function test_a_sole_unnamed_book_renders_exactly_the_project_title(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        $response = $this->actingAs($user)->get(route('books.story.overview', $book));

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
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

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
    }

    public function test_a_shallow_child_route_still_finds_its_project(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
    }
}
