<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Plotline;
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
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'Act Alpha']);

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
        [$project, $book] = $this->projectWithBook($user);

        $revised = Act::factory()->for($book)->create(['name' => 'Revised Act']);
        Act::factory()->for($book)->create(['name' => 'Untouched Act']);

        $this->revisionFor(Act::class, $revised->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('Revised Act');
        $response->assertDontSee('Untouched Act');
    }

    public function test_a_field_without_revisions_is_absent_under_a_revised_entity(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
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
        // A client-side filter narrows a large sidebar by name.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('x-model="filter"', false);
    }

    public function test_only_the_active_entitys_group_starts_open(): void
    {
        // Groups default-collapse to bound a big sidebar. Only the group that
        // holds the entity in view starts open.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
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

    public function test_the_entity_name_links_to_its_unfiltered_history(): void
    {
        // The sidebar's entity name is the way into the whole entity's history;
        // its field leaves are the same page with `?field=` set.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'Act Alpha']);

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        // The closing quote matters: the filtered leaf URL starts with the
        // unfiltered one, so a bare assertSee() of it would pass either way.
        $response->assertSee(
            'href="'.route('revisions.index', ['entity' => 'act', 'id' => $act->id]).'"',
            false,
        );
    }

    public function test_the_entity_level_history_page_marks_the_entity_row_active(): void
    {
        // With no `?field=`, the active row is the entity name itself — and its
        // group still starts open, exactly as on a field-filtered page.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'Act Alpha']);
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description');
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'notes');

        $response = $this->actingAs($user)->get(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id])
        );

        $response->assertOk();
        $response->assertSee('open: true', false);
        $response->assertSee('open: false', false);
        // The entity anchor is the one carrying aria-current, not a field leaf —
        // matched as one tag so the two cannot be satisfied by separate elements.
        $entityUrl = route('revisions.index', ['entity' => 'act', 'id' => $act->id]);

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote($entityUrl, '/').'"[^>]*aria-current="page"/',
            $response->getContent(),
        );
    }

    public function test_a_two_book_project_groups_manuscript_entities_under_the_right_book(): void
    {
        // Acts, chapters and scenes are grouped under their book; plotlines
        // and codex entries are project-scoped and stay outside the grouping.
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);
        $firstBook->update(['name' => 'Volume One']);

        $actOne = Act::factory()->for($firstBook)->create(['name' => 'Act One']);
        $chapterOne = Chapter::factory()->for($actOne)->create(['name' => 'Chapter One']);
        $sceneOne = Scene::factory()->for($chapterOne)->create(['name' => 'Scene One']);

        $actTwo = Act::factory()->for($secondBook)->create(['name' => 'Act Two']);
        $chapterTwo = Chapter::factory()->for($actTwo)->create(['name' => 'Chapter Two']);
        $sceneTwo = Scene::factory()->for($chapterTwo)->create(['name' => 'Scene Two']);

        $this->revisionFor(Act::class, $actOne->id, $project->id, 'description');
        $this->revisionFor(Act::class, $actTwo->id, $project->id, 'description');
        $this->revisionFor(Chapter::class, $chapterOne->id, $project->id, 'description');
        $this->revisionFor(Chapter::class, $chapterTwo->id, $project->id, 'description');
        $this->revisionFor(Scene::class, $sceneOne->id, $project->id, 'notes');
        $this->revisionFor(Scene::class, $sceneTwo->id, $project->id, 'notes');

        $plotline = Plotline::factory()->for($project)->create(['name' => 'Main Thread']);
        $this->revisionFor(Plotline::class, $plotline->id, $project->id, 'description');

        $codexEntry = CodexEntry::factory()->for($project)->create(['name' => 'Codex Star']);
        $this->revisionFor(CodexEntry::class, $codexEntry->id, $project->id, 'description');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        $response->assertSee('Chapter One');
        $response->assertSee('Chapter Two');
        $response->assertSee('Scene One');
        $response->assertSee('Scene Two');

        // The app's own top navigation repeats "Acts"/"Codex"/etc. as menu
        // labels, so ordering must be checked inside the revisions sidebar
        // only, never against the whole page.
        $this->assertMatchesRegularExpression('/<nav aria-label="Revision history".*?<\/nav>/s', $response->getContent());
        preg_match('/<nav aria-label="Revision history".*?<\/nav>/s', $response->getContent(), $matches);
        $sidebar = $matches[0];

        $positions = [
            'Volume One' => strpos($sidebar, 'Volume One'),
            'Act One' => strpos($sidebar, 'Act One'),
            'Volume Two' => strpos($sidebar, 'Volume Two'),
            'Act Two' => strpos($sidebar, 'Act Two'),
        ];
        foreach ($positions as $needle => $position) {
            $this->assertNotFalse($position, "\"{$needle}\" is missing from the revisions sidebar");
        }
        $this->assertTrue(
            $positions['Volume One'] < $positions['Act One']
                && $positions['Act One'] < $positions['Volume Two']
                && $positions['Volume Two'] < $positions['Act Two'],
            'Acts must be grouped under their own book, in book position order',
        );

        // No book heading sits between a project-scoped group's own heading
        // and its entity — plotlines/codex stay ungrouped.
        foreach (['Plotlines' => 'Main Thread', 'Codex' => 'Codex Star'] as $groupLabel => $entityName) {
            $groupPosition = strpos($sidebar, $groupLabel);
            $entityPosition = strpos($sidebar, $entityName);
            $this->assertNotFalse($groupPosition, "\"{$groupLabel}\" heading is missing from the revisions sidebar");
            $this->assertNotFalse($entityPosition, "\"{$entityName}\" is missing from the revisions sidebar");

            $between = substr($sidebar, $groupPosition, $entityPosition - $groupPosition);
            $this->assertStringNotContainsString('Volume One', $between);
            $this->assertStringNotContainsString('Volume Two', $between);
        }
    }

    public function test_a_revised_book_is_reachable_from_the_sidebar(): void
    {
        // A book's own front matter autosaves, so it has history like any other
        // entity. Without a sidebar row, that history has no link into it.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $book->update(['name' => 'Volume One']);

        $this->revisionFor(Book::class, $book->id, $project->id, 'dedication');

        $response = $this->actingAs($user)->get(route('projects.revisions.index', $project));

        $response->assertOk();
        preg_match('/<nav aria-label="Revision history".*?<\/nav>/s', $response->getContent(), $matches);
        $sidebar = $matches[0];

        $this->assertStringContainsString('Volume One', $sidebar);
        $this->assertStringContainsString(
            route('revisions.index', ['entity' => 'book', 'id' => $book->id, 'field' => 'dedication']),
            $sidebar,
        );
    }

    public function test_the_history_page_renders_the_browser_sidebar_with_the_active_field(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

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
