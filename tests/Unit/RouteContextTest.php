<?php

namespace Tests\Unit;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Scene;
use App\Models\User;
use App\Support\RouteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Builds {@see RouteContext} straight from a dispatched request — no
 * controller assertions, just the resolution itself. "Dispatched" rather
 * than hand-assembled: `$this->get(...)` runs the real route and
 * model-binding pipeline, then this test reads the resulting Request
 * straight out of the container, the same Request TrackActiveProject and
 * ProjectNavigation see.
 */
class RouteContextTest extends TestCase
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
     * RouteContext a view composer or middleware would have built for it.
     */
    private function contextFor(string $routeName, array $params = []): RouteContext
    {
        $this->actingAs($this->user)
            ->get(route($routeName, $params))
            ->assertSuccessful();

        /** @var Request $request */
        $request = $this->app->make('request');

        return RouteContext::resolve($request);
    }

    public function test_a_project_route_resolves_the_project_and_no_book(): void
    {
        [$project] = $this->projectWithBook($this->user);

        $context = $this->contextFor('projects.show', [$project]);

        $this->assertTrue($context->project->is($project));
        $this->assertNull($context->book);
    }

    public function test_an_act_route_resolves_its_book_and_project(): void
    {
        [$project, $book] = $this->projectWithBook($this->user);
        $act = Act::factory()->for($book)->create();

        $context = $this->contextFor('acts.edit', [$act]);

        $this->assertTrue($context->project->is($project));
        $this->assertTrue($context->book->is($book));
    }

    public function test_a_chapter_route_resolves_its_book_and_project(): void
    {
        [$project, $book] = $this->projectWithBook($this->user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $context = $this->contextFor('chapters.edit', [$chapter]);

        $this->assertTrue($context->project->is($project));
        $this->assertTrue($context->book->is($book));
    }

    public function test_a_scene_route_resolves_its_book_and_project(): void
    {
        [$project, $book] = $this->projectWithBook($this->user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $context = $this->contextFor('scenes.edit', [$scene]);

        $this->assertTrue($context->project->is($project));
        $this->assertTrue($context->book->is($book));
    }

    public function test_a_plotline_route_resolves_the_project_and_no_book(): void
    {
        [$project] = $this->projectWithBook($this->user);
        $plotline = Plotline::factory()->for($project)->create();

        $context = $this->contextFor('plotlines.edit', [$plotline]);

        $this->assertTrue($context->project->is($project));
        $this->assertNull($context->book);
    }

    public function test_an_event_route_resolves_the_project_and_no_book(): void
    {
        [$project] = $this->projectWithBook($this->user);
        $event = Event::factory()->for($project)->create();

        $context = $this->contextFor('events.edit', [$event]);

        $this->assertTrue($context->project->is($project));
        $this->assertNull($context->book);
    }

    public function test_a_codex_entry_route_resolves_the_project_and_no_book(): void
    {
        [$project] = $this->projectWithBook($this->user);
        $entry = CodexEntry::factory()->for($project)->create();

        $context = $this->contextFor('codex.edit', [$entry]);

        $this->assertTrue($context->project->is($project));
        $this->assertNull($context->book);
    }

    public function test_a_codex_attribute_route_resolves_the_project_and_no_book(): void
    {
        [$project] = $this->projectWithBook($this->user);
        $attribute = CodexAttribute::factory()->for($project)->create();

        $context = $this->contextFor('codex-attributes.edit', [$attribute]);

        $this->assertTrue($context->project->is($project));
        $this->assertNull($context->book);
    }

    public function test_an_off_route_page_resolves_neither(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertSuccessful();

        /** @var Request $request */
        $request = $this->app->make('request');

        $context = RouteContext::resolve($request);

        $this->assertNull($context->project);
        $this->assertNull($context->book);
    }

    /**
     * TrackActiveProject and the nav view composer both call
     * RouteContext::resolve() on every request, off the same route-bound
     * {scene}. The chapter -> act -> book -> project walk it runs must be
     * two belongsTo lookups the first time and none after — never a fresh
     * query per call, which would make every page cost one more query for
     * every sibling scene the book happens to hold.
     */
    public function test_resolving_the_same_route_twice_reuses_the_cached_walk(): void
    {
        [, $book] = $this->projectWithBook($this->user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($this->user)
            ->get(route('scenes.edit', $scene))
            ->assertOk();

        /** @var Request $request */
        $request = $this->app->make('request');

        // Warm the walk once — this is what the real dispatch above already
        // did, via TrackActiveProject; repeat it explicitly so the count
        // below isolates the *second* resolve() call, the one the nav view
        // composer makes.
        RouteContext::resolve($request);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        RouteContext::resolve($request);

        $this->assertSame(0, $queries);
    }
}
