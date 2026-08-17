<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the active-project persistence feature: the
 * `users.active_project_id` column and its FK behaviour, the TrackActiveProject
 * middleware, the project menu (a route project wins over the stored one), and
 * the login redirect.
 */
class ActiveProjectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A scene in a project owned by $user — the shallow child route
     * (/scenes/{scene}/edit) that carries no {project} parameter, so reaching it
     * proves the middleware walks scene -> chapter -> act -> project.
     */
    private function sceneIn(Project $project): Scene
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create();
    }

    public function test_opening_a_project_page_makes_it_the_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        $this->assertSame($project->id, $user->fresh()->active_project_id);
    }

    public function test_a_shallow_child_route_makes_its_project_active(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project);

        // /scenes/{scene}/edit has no {project} parameter: only the aggregate
        // walk can answer which project this page belongs to.
        $this->actingAs($user)->get(route('scenes.edit', $scene))->assertOk();

        $this->assertSame($project->id, $user->fresh()->active_project_id);
    }

    public function test_a_bookmark_into_another_project_switches_the_active_project(): void
    {
        // The scenario the whole design exists for: a deep link into project B,
        // with no {project} in its URL, must still activate B.
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $projectA->id])->save();

        $sceneInB = $this->sceneIn($projectB);

        $this->actingAs($user)->get(route('scenes.edit', $sceneInB))->assertOk();

        $this->assertSame($projectB->id, $user->fresh()->active_project_id);
    }

    public function test_opening_another_project_replaces_the_active_project(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $projectA->id])->save();

        $this->actingAs($user)->get(route('projects.show', $projectB))->assertOk();

        $this->assertSame($projectB->id, $user->fresh()->active_project_id);
    }

    public function test_a_page_with_no_project_in_its_route_leaves_the_active_project_set(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->assertSame($project->id, $user->fresh()->active_project_id);
    }

    public function test_a_non_owner_gets_a_403_and_does_not_activate_the_project(): void
    {
        // Asserting only the 403 would also pass against a middleware that wrote
        // on the way in — the column assertion is the one that catches it.
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)->get(route('projects.show', $project))->assertForbidden();

        $this->assertNull($intruder->fresh()->active_project_id);
    }

    public function test_revisiting_the_active_project_issues_no_update(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $userUpdates = 0;

        DB::listen(function ($query) use (&$userUpdates) {
            if (str_contains($query->sql, 'update "users"')) {
                $userUpdates++;
            }
        });

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        $this->assertSame(0, $userUpdates);
    }

    public function test_deleting_the_active_project_nulls_the_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $project->delete();

        $this->assertNull($user->fresh()->active_project_id);
    }

    public function test_deleting_a_different_project_leaves_the_active_project_untouched(): void
    {
        $user = User::factory()->create();
        $activeProject = Project::factory()->for($user)->create();
        $otherProject = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $activeProject->id])->save();

        $otherProject->delete();

        $this->assertSame($activeProject->id, $user->fresh()->active_project_id);
    }

    public function test_deleting_the_user_still_succeeds_with_an_active_project_set(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_the_dashboard_shows_the_project_menu_for_the_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // The point of the feature: the project menu renders on a page whose URL
        // names no project, so returning to the work is one click.
        $this->assertStringContainsString('href="'.e(route('books.story.overview', $book)).'"', $html);
        $this->assertStringContainsString('Melusine', $html);
    }

    public function test_the_dashboard_highlights_no_section(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // The `*Active` flags match the route, not the stored project: no section
        // is open on the dashboard, so nothing may claim to be current. Scoped to
        // anchors so a breadcrumb's own <span aria-current> is not counted.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*aria-current="page"/', $html);
    }

    public function test_the_dashboard_asks_for_a_project_when_none_is_active(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Choose a project', $html);
        $this->assertStringNotContainsString('href="'.e(route('books.story.overview', $book)).'"', $html);
    }

    public function test_deleting_the_active_project_returns_the_dashboard_to_choose_a_project(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $user->forceFill(['active_project_id' => $project->id])->save();

        $project->delete();

        // The FK nulls the column, so the fallback has nothing to fall back to —
        // the nav must not render links to a project that no longer exists.
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Choose a project', $html);
        $this->assertStringNotContainsString('href="'.e(route('books.story.overview', $book)).'"', $html);
    }

    public function test_the_route_project_wins_over_the_stored_one(): void
    {
        $user = User::factory()->create();
        $stored = Project::factory()->for($user)->create();
        $visited = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $stored->id])->save();

        // The stored project is a fallback, never an override: opening another
        // project's page must build the menu from the URL's project.
        $html = $this->actingAs($user)->get(route('projects.show', $visited))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e(route('books.story.overview', $visited->books()->first())).'"', $html);
        $this->assertStringNotContainsString('href="'.e(route('books.story.overview', $stored->books()->first())).'"', $html);
    }

    public function test_opening_a_book_page_records_it_as_the_last_book(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $this->actingAs($user)->get(route('books.story.overview', $book))->assertOk();

        $this->assertSame($book->id, $project->fresh()->last_book_id);
    }

    public function test_a_403_on_a_book_page_does_not_record_it(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$project, $book] = $this->projectWithBook($owner);

        $this->actingAs($intruder)->get(route('books.story.overview', $book))->assertForbidden();

        $this->assertNull($project->fresh()->last_book_id);
    }

    public function test_a_page_with_no_book_in_its_route_leaves_last_book_id_set(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $project->forceFill(['last_book_id' => $book->id])->save();

        // The timeline carries no {book}: it must not CLEAR last_book_id.
        $this->actingAs($user)->get(route('projects.timeline.home', $project))->assertOk();

        $this->assertSame($book->id, $project->fresh()->last_book_id);
    }

    public function test_switching_books_replaces_the_last_book(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();
        $project->forceFill(['last_book_id' => $firstBook->id])->save();

        $this->actingAs($user)->get(route('books.story.overview', $secondBook))->assertOk();

        $this->assertSame($secondBook->id, $project->fresh()->last_book_id);
    }

    public function test_a_bare_login_redirects_to_the_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('projects.show', $project, absolute: false));
    }

    public function test_a_bare_login_with_no_active_project_redirects_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_after_a_deep_link_still_lands_on_the_intended_url(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $activeProject = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $activeProject->id])->save();

        // Hitting an auth-protected URL while a guest stores it as the
        // "intended" destination; login must still honour that over the
        // active project.
        $this->get(route('projects.show', $project))->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('projects.show', $project, absolute: false));
    }
}
