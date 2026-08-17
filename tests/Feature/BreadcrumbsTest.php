<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The rendered breadcrumb band, end to end: the view composer builds a trail,
 * the layout swaps its old header band for the two-column breadcrumb band, and
 * off-project routes fall back to their own header slot.
 *
 * The exact trail ordering per route is pinned in {@see \Tests\Unit\BreadcrumbsTest};
 * this asserts the *integration* — that the band renders (or doesn't), keeps the
 * W3C a11y contract, reads the live model, and never leaks a label past a 403.
 */
class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** The inner HTML of the single breadcrumb <nav>, or '' if none rendered. */
    private function breadcrumbNav(string $html): string
    {
        return preg_match('/<nav aria-label="Breadcrumb".*?<\/nav>/s', $html, $matches)
            ? $matches[0]
            : '';
    }

    public function test_codex_index_renders_its_section_trail_in_the_band(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $html = $this->actingAs($this->user)
            ->get(route('projects.codex.index', [$project, CodexEntryType::Character->routeKey()]))
            ->assertOk()
            ->getContent();

        $nav = $this->breadcrumbNav($html);

        $this->assertStringContainsString(__('Dashboard'), $nav);
        $this->assertStringContainsString(__('Codex'), $nav);
        $this->assertStringContainsString(CodexEntryType::Character->pluralLabel(), $nav);
        // The sub-index is the current leaf.
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>\s*'.preg_quote(CodexEntryType::Character->pluralLabel(), '/').'/',
            $nav,
        );
    }

    public function test_codex_edit_leaf_names_the_id_and_links_the_sub_index(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $entry = CodexEntry::factory()->for($project)->organization()->create(['name' => 'The Silver Table']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('codex.edit', $entry))->assertOk()->getContent()
        );

        $this->assertStringContainsString(
            __('Edit :thing :id', ['thing' => Str::lower(CodexEntryType::Organization->label()), 'id' => $entry->id]),
            $nav,
        );
        // The entry name must not appear in the leaf — id only, for now.
        $this->assertStringNotContainsString('The Silver Table', $nav);
        // Sub-index becomes a link now that it is an ancestor.
        $this->assertStringContainsString(
            'href="'.e(route('projects.codex.index', [$project, 'organizations'])).'"',
            $nav,
        );
    }

    public function test_a_create_route_renders_an_action_precise_leaf(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('books.scenes.create', $book))->assertOk()->getContent()
        );

        $this->assertStringContainsString(__('Story'), $nav);
        $this->assertStringContainsString(__('Scenes'), $nav);
        $this->assertStringContainsString(__('New :thing', ['thing' => __('scene')]), $nav);
    }

    public function test_an_edit_route_leaf_uses_the_bound_models_id(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $event = Event::factory()->for($project)->create(['title' => 'The Siege Begins']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('events.edit', $event))->assertOk()->getContent()
        );

        $this->assertStringContainsString(__('Timeline'), $nav);
        $this->assertStringContainsString(
            __('Edit :thing :id', ['thing' => __('event'), 'id' => $event->id]),
            $nav,
        );
    }

    public function test_the_edit_leaf_renders_the_id_not_the_models_name(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['name' => 'A Named Act']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('acts.edit', $act))->assertOk()->getContent()
        );

        $this->assertStringContainsString(
            __('Edit :thing :id', ['thing' => __('act'), 'id' => $act->id]),
            $nav,
        );
        $this->assertStringNotContainsString('A Named Act', $nav);
    }

    public function test_the_revisions_browser_renders_a_tools_trail(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('projects.revisions.index', $project))->assertOk()->getContent()
        );

        $this->assertStringContainsString(__('Tools'), $nav);
        $this->assertStringContainsString(__('Revisions'), $nav);
    }

    public function test_the_revisions_history_page_renders_the_exception_trail(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['name' => 'The Curse Begins']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)
                ->get(route('revisions.index', ['entity' => 'act', 'id' => $act->id]))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString(__('Dashboard'), $nav);
        $this->assertStringContainsString(__('Tools'), $nav);
        // Tools now links to its section stub (consistent with the central builder).
        $this->assertStringContainsString('href="'.e(route('projects.tools.home', $project)).'"', $nav);
        $this->assertStringContainsString('href="'.e(route('projects.revisions.index', $project)).'"', $nav);
        $this->assertStringContainsString('The Curse Begins', $nav);
        $this->assertStringContainsString(__('History'), $nav);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
    }

    public function test_the_revisions_compare_page_renders_the_exception_trail(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['name' => 'The Curse Begins']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)
                ->get(route('revisions.compare', ['entity' => 'act', 'id' => $act->id]))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString(__('Tools'), $nav);
        $this->assertStringContainsString('href="'.e(route('projects.revisions.index', $project)).'"', $nav);
        $this->assertStringContainsString('The Curse Begins', $nav);
        $this->assertStringContainsString(__('Compare'), $nav);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
    }

    public function test_a_403_on_a_revisions_history_route_does_not_leak_the_label(): void
    {
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($other);
        $act = Act::factory()->for($book)->create(['name' => 'Concealed History Title']);

        $this->actingAs($this->user)
            ->get(route('revisions.index', ['entity' => 'act', 'id' => $act->id]))
            ->assertForbidden()
            ->assertDontSee('Concealed History Title');
    }

    public function test_search_renders_a_two_crumb_trail_with_no_section(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('projects.search.index', $project))->assertOk()->getContent()
        );

        $this->assertStringContainsString(__('Search'), $nav);
        // Dashboard + Search only — two list items, no section crumb.
        $this->assertSame(2, substr_count($nav, '<li'));
    }

    public function test_the_dashboard_renders_a_single_current_crumb(): void
    {
        $project = Project::factory()->for($this->user)->create();

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('projects.show', $project))->assertOk()->getContent()
        );

        $this->assertSame(1, substr_count($nav, '<li'));
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        $this->assertStringContainsString(__('Dashboard'), $nav);
    }

    public function test_the_book_crumb_appears_on_story_pages_when_the_book_is_named(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('books.acts.index', $book))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(e(route('books.show', $book)), '/').'"[^>]*>\s*Volume One\s*<\/a>/',
            $nav,
        );
    }

    public function test_the_book_crumb_is_absent_when_the_book_has_no_name_of_its_own(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('books.acts.index', $book))->assertOk()->getContent()
        );

        // Not a plain "does not contain" check: books.show's URL is a path
        // prefix of every other book-scoped route (books.story.home,
        // books.acts.index, …), so it is trivially a substring of those hrefs
        // too. Anchor on the closing quote to match the full href exactly.
        $this->assertDoesNotMatchRegularExpression(
            '/href="'.preg_quote(e(route('books.show', $book)), '/').'"/',
            $nav,
        );
    }

    public function test_the_book_crumb_is_absent_on_codex_timeline_and_tools_even_when_the_book_is_named(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('projects.codex.home', $project))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('Volume One', $nav);
    }

    public function test_books_show_renders_the_book_as_the_current_leaf_when_named(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('books.show', $book))->assertOk()->getContent()
        );

        $this->assertStringContainsString(__('Dashboard'), $nav);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*Volume One/', $nav);
    }

    public function test_books_show_renders_no_band_when_the_book_has_no_name(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $html = $this->actingAs($this->user)->get(route('books.show', $book))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Breadcrumb"', $html);
    }

    public function test_section_crumbs_link_to_their_section_stub(): void
    {
        [, $book] = $this->projectWithBook($this->user);

        $nav = $this->breadcrumbNav(
            $this->actingAs($this->user)->get(route('books.acts.index', $book))->assertOk()->getContent()
        );

        // The Story section crumb now links to its stub landing (was plain text).
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(e(route('books.story.home', $book)), '/').'"[^>]*>\s*'.preg_quote(__('Story'), '/').'\s*<\/a>/',
            $nav,
        );
    }

    public function test_the_band_carries_exactly_one_landmark_one_current_and_hidden_separators(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();

        $html = $this->actingAs($this->user)
            ->get(route('acts.edit', $act))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Breadcrumb"'));

        // Dashboard › Story › Acts › Edit … — four crumbs, three separators, all hidden.
        // (aria-current is scoped to the nav: the primary menu also marks its active link.)
        $nav = $this->breadcrumbNav($html);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        $this->assertSame(4, substr_count($nav, '<li'));
        $this->assertSame(3, substr_count($nav, 'aria-hidden="true"'));
    }

    public function test_the_band_colours_its_text_so_plain_crumbs_are_readable(): void
    {
        // Regression: the band sets bg-nav-raised; without text-nav-content the
        // plain <span> crumbs (section triggers + current leaf) inherited the dark
        // body text and rendered near-invisibly on it (blue on lighter blue).
        [, $book] = $this->projectWithBook($this->user);

        $html = $this->actingAs($this->user)
            ->get(route('books.acts.index', $book))
            ->assertOk()
            ->getContent();

        // The standalone `text-nav-content` token (space-delimited), not the
        // `[&_a]:text-nav-content` variant that only colours links — that one
        // was present while the bug existed, so match the bare class.
        $this->assertStringContainsString('shadow-sm text-nav-content', $html);
    }

    public function test_no_band_and_header_slot_intact_on_the_root_dashboard(): void
    {
        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('aria-label="Breadcrumb"', $html);
        // The page keeps its own header-slot heading.
        $this->assertStringContainsString(__('Dashboard'), $html);
    }

    public function test_no_band_on_the_profile_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_no_band_on_an_admin_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_a_403_does_not_leak_the_bound_models_label(): void
    {
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($other);
        $act = Act::factory()->for($book)->create(['name' => 'Concealed Chapter Title']);

        $this->actingAs($this->user)
            ->get(route('acts.edit', $act))
            ->assertForbidden()
            ->assertDontSee('Concealed Chapter Title');
    }
}
