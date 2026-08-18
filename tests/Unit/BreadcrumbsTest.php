<?php

namespace Tests\Unit;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Support\Breadcrumbs;
use App\Support\Crumb;
use App\Support\ProjectNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Build breadcrumb trails from dispatched requests. */
class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Dispatches a real GET to $routeName as the owning user and returns the
     * Breadcrumbs a view composer would have built for that exact request.
     */
    private function breadcrumbsFor(string $routeName, array $params = []): Breadcrumbs
    {
        $this->actingAs($this->user)
            ->get(route($routeName, $params))
            ->assertSuccessful();

        /** @var Request $request */
        $request = $this->app->make('request');

        return new Breadcrumbs(new ProjectNavigation($request), $request);
    }

    /**
     * @return list<array{string, ?string, bool}>
     */
    private function summarize(Breadcrumbs $breadcrumbs): array
    {
        return array_map(
            fn (Crumb $crumb) => [$crumb->label, $crumb->url, $crumb->current],
            iterator_to_array($breadcrumbs),
        );
    }

    public function test_dashboard_root_is_a_single_current_crumb(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $breadcrumbs = $this->breadcrumbsFor('projects.show', [$project]);

        $this->assertSame([
            [__('Dashboard'), null, true],
        ], $this->summarize($breadcrumbs));
    }

    public function test_an_index_route_makes_the_sub_index_the_current_leaf(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $breadcrumbs = $this->breadcrumbsFor('books.acts.index', [$book]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(__('Dashboard'), $rows[0][0]);
        $this->assertNotNull($rows[0][1]);
        $this->assertFalse($rows[0][2]);

        $this->assertSame(__('Story'), $rows[1][0]);
        // The section crumb now links to its stub landing.
        $this->assertSame(route('books.story.home', $book), $rows[1][1]);
        $this->assertFalse($rows[1][2]);

        $this->assertSame([__('Acts'), null, true], $rows[2]);
        $this->assertCount(3, $rows, 'the index crumb is the leaf — no duplicate current crumb');
    }

    public function test_a_named_book_prepends_a_book_crumb_linking_the_book_home(): void
    {
        [, $book] = $this->projectWithBook($this->user);
        $book->update(['name' => 'Volume One']);

        $rows = $this->summarize($this->breadcrumbsFor('books.acts.index', [$book]));

        $this->assertSame(['Volume One', route('books.show', $book), false], $rows[1]);
        $this->assertSame([__('Story'), route('books.story.home', $book), false], $rows[2]);
        $this->assertSame([__('Acts'), null, true], $rows[3]);
        $this->assertCount(4, $rows);
    }

    public function test_books_show_is_a_two_crumb_trail_naming_the_book_when_it_has_one(): void
    {
        [$project, $book] = $this->projectWithBook($this->user);
        $book->update(['name' => 'Volume One']);

        $this->assertSame([
            [__('Dashboard'), route('projects.show', $project), false],
            ['Volume One', null, true],
        ], $this->summarize($this->breadcrumbsFor('books.show', [$book])));
    }

    public function test_books_show_yields_no_trail_when_the_book_has_no_name(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $breadcrumbs = $this->breadcrumbsFor('books.show', [$book]);

        $this->assertTrue($breadcrumbs->isEmpty());
    }

    public function test_a_section_stub_page_is_a_two_crumb_trail_with_the_section_as_current_leaf(): void
    {
        [$project, $book] = $this->projectWithBook($this->user);

        foreach ([
            'books.story.home' => [__('Story'), $book],
            'projects.timeline.home' => [__('Timeline'), $project],
            'projects.codex.home' => [__('Codex'), $project],
            'projects.tools.home' => [__('Tools'), $project],
        ] as $route => [$label, $parent]) {
            $rows = $this->summarize($this->breadcrumbsFor($route, [$parent]));

            $this->assertSame([__('Dashboard'), route('projects.show', $project), false], $rows[0], $route);
            $this->assertSame([$label, null, true], $rows[1], $route);
            $this->assertCount(2, $rows, $route);
        }
    }

    public function test_the_story_overview_trail_links_story_and_names_overview(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $rows = $this->summarize($this->breadcrumbsFor('books.story.overview', [$book]));

        $this->assertSame([__('Story'), route('books.story.home', $book), false], $rows[1]);
        $this->assertSame([__('Overview'), null, true], $rows[2]);
        $this->assertCount(3, $rows);
    }

    public function test_a_create_route_leaf_names_the_action(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $breadcrumbs = $this->breadcrumbsFor('books.scenes.create', [$book]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame([__('Scenes'), route('books.scenes.index', $book), false], $rows[2]);
        $this->assertSame([__('New :thing', ['thing' => __('scene')]), null, true], $rows[3]);
    }

    public function test_an_edit_route_leaf_is_action_precise_and_uses_the_bound_models_id(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['name' => 'The Rising Storm']);

        $breadcrumbs = $this->breadcrumbsFor('acts.edit', [$act]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame([__('Acts'), route('books.acts.index', $book), false], $rows[2]);
        $this->assertSame(
            [__('Edit :thing :id', ['thing' => __('act'), 'id' => $act->id]), null, true],
            $rows[3],
        );
    }

    public function test_the_edit_leaf_names_the_id_not_the_models_name(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'A Chapter Name']);

        $breadcrumbs = $this->breadcrumbsFor('chapters.edit', [$chapter]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(
            [__('Edit :thing :id', ['thing' => __('chapter'), 'id' => $chapter->id]), null, true],
            end($rows),
        );
        $this->assertStringNotContainsString('A Chapter Name', end($rows)[0]);
    }

    public function test_event_leaf_uses_the_id(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $event = Event::factory()->for($project)->create(['title' => 'The Siege Begins']);

        $breadcrumbs = $this->breadcrumbsFor('events.edit', [$event]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(__('Timeline'), $rows[1][0]);
        $this->assertSame(
            [__('Edit :thing :id', ['thing' => __('event'), 'id' => $event->id]), null, true],
            end($rows),
        );
    }

    public function test_codex_index_leaf_uses_the_active_types_plural_label(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $breadcrumbs = $this->breadcrumbsFor('projects.codex.index', [$project, CodexEntryType::Character->routeKey()]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(__('Codex'), $rows[1][0]);
        $this->assertSame([CodexEntryType::Character->pluralLabel(), null, true], $rows[2]);
    }

    public function test_codex_edit_leaf_names_the_type_singular_and_the_id(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $entry = CodexEntry::factory()->for($project)->organization()->create(['name' => 'The Silver Table']);

        $breadcrumbs = $this->breadcrumbsFor('codex.edit', [$entry]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(
            [CodexEntryType::Organization->pluralLabel(), route('projects.codex.index', [$project, 'organizations']), false],
            $rows[2],
        );
        $this->assertSame(
            [__('Edit :thing :id', ['thing' => Str::lower(CodexEntryType::Organization->label()), 'id' => $entry->id]), null, true],
            $rows[3],
        );
    }

    public function test_codex_attribute_edit_leaf_uses_the_lowercase_thing_and_id(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $attribute = CodexAttribute::factory()->for($project)->create();

        $breadcrumbs = $this->breadcrumbsFor('codex-attributes.edit', [$attribute]);
        $rows = $this->summarize($breadcrumbs);

        $this->assertSame(
            [__('Edit :thing :id', ['thing' => __('attribute'), 'id' => $attribute->id]), null, true],
            end($rows),
        );
    }

    public function test_search_trail_has_no_section_crumb(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $breadcrumbs = $this->breadcrumbsFor('projects.search.index', [$project]);

        $this->assertSame([
            [__('Dashboard'), route('projects.show', $project), false],
            [__('Search'), null, true],
        ], $this->summarize($breadcrumbs));
    }

    public function test_a_non_project_route_yields_an_empty_trail(): void
    {
        $breadcrumbs = $this->breadcrumbsFor('dashboard');

        $this->assertTrue($breadcrumbs->isEmpty());
        $this->assertCount(0, $breadcrumbs);
        $this->assertSame([], $this->summarize($breadcrumbs));
    }

    /**
     * Tools is the one section whose sub-pages are not a single entity family,
     * so the trail names each one explicitly. A Tools route with no branch of
     * its own gets no trail — the layout then shows the page's own header. The
     * catch-all this replaces labelled every such page "Revisions".
     *
     * The route is registered here because the bug only appears for a Tools
     * route that does not exist yet.
     */
    public function test_an_unmapped_tools_route_yields_no_trail(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Route::middleware(['web', 'auth'])
            ->get('/projects/{project}/tools/unmapped', fn (Project $project) => '')
            ->name('projects.tools.unmapped');

        // The name lookup is built once at boot, so a route added here is
        // invisible to route() until it is rebuilt.
        Route::getRoutes()->refreshNameLookups();

        $breadcrumbs = $this->breadcrumbsFor('projects.tools.unmapped', [$project]);

        $this->assertTrue($breadcrumbs->isEmpty(), 'An unmapped Tools route must not inherit the Revisions label.');
    }

    public function test_the_revisions_browser_keeps_its_tools_trail(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $this->assertSame([
            [__('Dashboard'), route('projects.show', $project), false],
            [__('Tools'), route('projects.tools.home', $project), false],
            [__('Revisions'), null, true],
        ], $this->summarize($this->breadcrumbsFor('projects.revisions.index', [$project])));
    }
}
