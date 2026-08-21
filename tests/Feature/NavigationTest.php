<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\ProjectNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Test navigation state through semantic markers and destination links. */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function chapterFor(User $user): Chapter
    {
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        return Chapter::factory()->for($act)->create();
    }

    /**
     * Assert that an <a> anchor pointing at $href carries aria-current="page".
     * The dropdown-link renders `<a class="…" href="…" aria-current="page">`,
     * so href precedes the marker.
     */
    private function assertLinkIsCurrent(string $html, string $href, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(e($href), '/').'"[^>]*aria-current="page"/',
            $html,
            $message,
        );
    }

    /**
     * Assert that the anchor for $href is present but is NOT the aria-current one.
     */
    private function assertLinkIsNotCurrent(string $html, string $href, string $message = ''): void
    {
        $this->assertStringContainsString('href="'.e($href).'"', $html, $message);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="'.preg_quote(e($href), '/').'"[^>]*aria-current="page"/',
            $html,
            $message,
        );
    }

    public function test_the_active_story_item_is_marked_on_a_story_page(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('books.scenes.index', $book));
    }

    public function test_a_non_active_sibling_is_not_marked(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->getContent();

        // Guards against over-broad matchers ("everything highlights"): on the
        // Scenes page the Acts item must be present but not current.
        $this->assertLinkIsNotCurrent($html, route('books.acts.index', $book));
    }

    public function test_a_child_route_still_highlights_its_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $scene = Scene::factory()->for($chapter)->create();

        // scenes.edit is matched by the `scenes.*` half of the matcher.
        $html = $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('books.scenes.index', $book));
    }

    public function test_the_story_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        // On a Story page the trigger swaps to nav-link's active look.
        $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->assertSee('text-nav-content border-accent', false);

        // On Home the Story trigger is inactive; the active-trigger token, whose
        // class order is unique to the trigger, must be absent.
        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('text-nav-content border-accent', false);
    }

    /**
     * Assert that the desktop dropdown trigger button labeled $label carries the
     * active class token. Triggers are <button>s (no aria-current), so the active
     * class token is the sanctioned hook. The regex ties the token to the specific
     * button by matching up to its label, so "Codex active" cannot be satisfied by
     * a different active trigger on the same page.
     */
    private function assertTriggerIsActive(string $html, string $label, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/<button[^>]*text-nav-content border-accent[^>]*>\s*'.preg_quote($label, '/').'/',
            $html,
            $message,
        );
    }

    /**
     * Assert that the trigger labeled $label is present but NOT in its active state.
     */
    private function assertTriggerIsNotActive(string $html, string $label, string $message = ''): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*text-nav-content border-accent[^>]*>\s*'.preg_quote($label, '/').'/',
            $html,
            $message,
        );
    }

    public function test_the_active_codex_type_is_marked_on_a_codex_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->getContent();

        // The enum-aware matcher must mark only the visited type, not its siblings
        // and not the Attributes item.
        $this->assertLinkIsCurrent($html, route('projects.codex.index', [$project, 'characters']));
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'locations']));
        $this->assertLinkIsNotCurrent($html, route('projects.codex-attributes.index', $project));
    }

    public function test_the_attributes_item_is_marked_and_no_type_is(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.codex-attributes.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.codex-attributes.index', $project));
        // Attributes and the codex types are distinct namespaces — no type highlights here.
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'characters']));
    }

    public function test_the_active_timeline_item_is_marked_on_a_plotlines_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.plotlines.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.plotlines.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.events.index', $project));
    }

    public function test_the_codex_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        // On a Codex page the Codex trigger is active; the Story trigger is not.
        $codexHtml = $this->actingAs($user)
            ->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsActive($codexHtml, 'Codex');
        $this->assertTriggerIsNotActive($codexHtml, 'Story');

        // On a Story page the Codex trigger falls back to its inactive state.
        $storyHtml = $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsNotActive($storyHtml, 'Codex');
    }

    public function test_the_timeline_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.plotlines.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsActive($html, 'Timeline');
        $this->assertTriggerIsNotActive($html, 'Codex');
    }

    public function test_no_dropdown_item_is_marked_on_home(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // Proves the `active` default is false, so a dropdown the page does not
        // match stays unaffected. Scoped to anchors so the breadcrumb's own
        // <span aria-current> is not counted.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*aria-current="page"/', $html);
    }

    public function test_the_search_link_is_marked_on_the_search_page(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $html = $this->actingAs($user)
            ->get(route('projects.search.index', $project))
            ->assertOk()
            ->getContent();

        // Search is the active top-level link; every other section stays inactive.
        $this->assertLinkIsCurrent($html, route('projects.search.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.show', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.plotlines.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'characters']));
        $this->assertLinkIsNotCurrent($html, route('books.story.overview', $book));
    }

    public function test_the_search_link_is_present_but_not_marked_on_a_non_search_page(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->getContent();

        // The link is always in the nav, but only current when on a search route.
        $this->assertLinkIsNotCurrent($html, route('projects.search.index', $project));
    }

    public function test_both_menus_mark_the_same_active_item(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.revisions.index', $project))
            ->assertOk()
            ->getContent();

        $href = preg_quote(e(route('projects.revisions.index', $project)), '/');

        // Desktop: the dropdown item is the aria-current one.
        $this->assertLinkIsCurrent($html, route('projects.revisions.index', $project));

        // Responsive: the same section is highlighted in the collapsed menu.
        // Tools is the regression case — its flag used to be computed only in
        // the desktop block and read by the responsive block through PHP scope
        // leak, so reordering the file would have silently dropped this.
        // Lookaheads because attribute order in the rendered <a> is not ours.
        // `border-accent text-start` is unique to responsive-nav-link active.
        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*href="'.$href.'")(?=[^>]*border-accent text-start)[^>]*>/',
            $html,
            'Revisions should be highlighted in the responsive menu too.',
        );
    }

    public function test_the_search_link_points_at_the_project_search_route(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // The entry point resolves to the project-scoped search index.
        $this->assertStringContainsString(
            'href="'.e(route('projects.search.index', $project)).'"',
            $html,
        );
    }

    public function test_the_picker_names_the_open_book_and_offers_other_projects(): void
    {
        $user = User::factory()->create();
        $open = Project::factory()->for($user)->create(['name' => 'The open one']);
        $other = Project::factory()->for($user)->create(['name' => 'Another one']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $open))
            ->assertOk()
            ->getContent();

        // The trigger names the open book — its sole book has none of its own,
        // so it falls back to the project name.
        $this->assertStringContainsString('The open one', $html);

        // Another project renders as an unlinked heading, its (sole, unnamed)
        // book listed beneath it and linking to the book, not the project.
        $this->assertStringContainsString('Another one', $html);
        $this->assertStringContainsString('href="'.e(route('books.select', $other->books()->first())).'"', $html);
        $this->assertStringNotContainsString('href="'.e(route('projects.show', $other)).'"', $html);

        // "All projects" is the overflow route out of a capped list, so it is
        // part of the contract, not decoration.
        $this->assertStringContainsString('href="'.e(route('projects.index')).'"', $html);

        // x-dropdown maps only the legacy width="48" and passes anything else
        // through verbatim, so width="56" would emit a junk `56` class and leave
        // the panel unsized. Pin the rendered class, not the attribute.
        $this->assertMatchesRegularExpression('/class="absolute z-50 mt-0 w-56 /', $html);
    }

    public function test_the_picker_lists_every_book_in_the_current_project_and_a_manage_books_link(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $firstBook->update(['name' => 'Volume One']);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);

        $html = $this->actingAs($user)
            ->get(route('books.show', $firstBook))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.e(route('books.select', $firstBook)).'"', $html);
        $this->assertStringContainsString('href="'.e(route('books.select', $secondBook)).'"', $html);
        $this->assertStringContainsString('href="'.e(route('projects.books.index', $project)).'"', $html);
    }

    public function test_the_open_book_stays_listed_and_marked_active(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $firstBook->update(['name' => 'Volume One']);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);

        $html = $this->actingAs($user)
            ->get(route('books.show', $firstBook))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('books.select', $firstBook));
        $this->assertLinkIsNotCurrent($html, route('books.select', $secondBook));
    }

    public function test_a_sole_unnamed_book_shows_one_picker_line(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // No muted project sub-line under the trigger while the sole book
        // carries no name of its own.
        $this->assertDoesNotMatchRegularExpression(
            '/text-xs font-normal text-nav-content-muted">\s*'.preg_quote(e($project->name), '/').'/',
            $html,
        );
    }

    public function test_a_named_book_shows_the_project_beneath_it_in_the_trigger(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $book->update(['name' => 'Volume One']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Volume One', $html);
        $this->assertMatchesRegularExpression(
            '/text-xs font-normal text-nav-content-muted">\s*'.preg_quote(e($project->name), '/').'/',
            $html,
        );
    }

    public function test_the_pickers_other_project_book_list_is_capped_at_five(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $other = Project::factory()->for($user)->create(['name' => 'Aaa other']);

        // $other already has one auto-created book (position 1); five more
        // push it to six, one past the cap.
        foreach (range(1, 5) as $i) {
            Book::factory()->for($other)->create(['name' => "Book $i"]);
        }

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Book 1', $html);
        $this->assertStringContainsString('Book 4', $html);
        $this->assertStringNotContainsString('Book 5', $html);
    }

    public function test_the_picker_stays_memoized_across_both_menus(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);
        $this->projectWithBook($user);

        // Build the same object the view composer builds, off the same
        // dispatched request (the pattern Tests\Unit\BreadcrumbsTest uses),
        // rather than counting "books" queries in the raw SQL log: the page
        // itself already issues a few unrelated ones (ProjectController's own
        // book list, ProjectNavigation::$book's plain ->first() fallback),
        // which would make a log-scraping count fragile and miss the point.
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $navigation = new ProjectNavigation($this->app->make('request'));

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        // The desktop panel and the responsive menu each call both methods
        // once; without memoization that would double every query below.
        // projectBooks() is one query; otherProjects() is two (Eloquent
        // eager-loads its books relation as a separate query from the
        // projects themselves) — three total, however many times either
        // method is called.
        $navigation->projectBooks();
        $navigation->projectBooks();
        $navigation->otherProjects();
        $navigation->otherProjects();

        $this->assertSame(3, $queries);
    }

    public function test_the_picker_never_lists_another_users_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $stranger = Project::factory()->for(User::factory())->create(['name' => 'Not yours']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // The list is built off the signed-in user's own relation; a leak here
        // would hand out another writer's project names, not just a bad link.
        $this->assertStringNotContainsString('Not yours', $html);
        $this->assertStringNotContainsString(route('projects.show', $stranger), $html);
    }

    public function test_the_picker_caps_its_list_and_leaves_the_rest_to_all_projects(): void
    {
        $user = User::factory()->create();
        $open = Project::factory()->for($user)->create(['name' => 'Aaa open']);

        // Named so alphabetical order is predictable: the first five sortable
        // names are offered, the sixth is not.
        foreach (['Bbb', 'Ccc', 'Ddd', 'Eee', 'Fff', 'Ggg'] as $name) {
            Project::factory()->for($user)->create(['name' => $name]);
        }

        $html = $this->actingAs($user)
            ->get(route('projects.show', $open))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Bbb', $html);
        $this->assertStringContainsString('Fff', $html);
        $this->assertStringNotContainsString('Ggg', $html);
    }

    public function test_the_picker_asks_for_a_project_when_none_is_open(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Somewhere else']);

        // The dashboard is inside the app layout but belongs to no project, and
        // this user has none stored either: the trigger has nothing to name, so
        // it prompts instead of rendering blank. Factoried users have a null
        // active_project_id, which is why this case is reachable at all — the
        // test below makes the other half of that guarantee explicit.
        $html = $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Choose a project', $html);
        $this->assertStringContainsString('Somewhere else', $html);
    }

    public function test_the_menu_renders_off_route_from_the_stored_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The stored one']);
        $user->forceFill(['active_project_id' => $project->id])->save();

        $html = $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->getContent();

        // Every "no project menu here" case above holds only because a factoried
        // user has no active project. Set it, and the menu appears off-route —
        // stated once so that behaviour stops being accidental.
        $this->assertStringContainsString('The stored one', $html);
        $this->assertStringContainsString('href="'.e(route('projects.plotlines.index', $project)).'"', $html);
        $this->assertStringNotContainsString('Choose a project', $html);
    }

    public function test_both_menus_share_one_query_for_the_picker_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Project::factory()->for($user)->create();

        // The desktop panel and the responsive menu each render the list. Without
        // memoization in ProjectNavigation that is a second identical query on
        // every authenticated page in the app.
        $pickerQueries = 0;

        DB::listen(function ($query) use (&$pickerQueries) {
            if (str_contains($query->sql, 'from "projects"') && str_contains($query->sql, 'order by "name"')) {
                $pickerQueries++;
            }
        });

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        $this->assertSame(1, $pickerQueries);
    }
}
