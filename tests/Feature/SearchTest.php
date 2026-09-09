<?php

namespace Tests\Feature;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\SearchResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the project search HTTP layer (route + SearchController +
 * SearchRequest). The searching itself is covered by ProjectSearchTest; these
 * tests prove the controller authorizes, handles the blank-query landing state
 * without erroring, validates `mode`, and wires ProjectSearch into the view with
 * the AND default applied.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_search_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.search.index', $project))
            ->assertForbidden();
    }

    public function test_an_empty_query_renders_the_form_with_no_results_and_no_errors(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', $project));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
        // No search was run: the controller passes results = null (landing state).
        $this->assertNull($response->viewData('results'));
    }

    public function test_a_blank_whitespace_query_is_treated_as_no_search(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => '   ']));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
        $this->assertNull($response->viewData('results'));
    }

    public function test_an_invalid_mode_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'dragon', 'mode' => 'not-a-mode']))
            ->assertSessionHasErrors('mode');
    }

    public function test_a_book_from_another_project_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $otherBook = Book::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'book' => $otherBook->id]))
            ->assertSessionHasErrors('book');
    }

    public function test_a_chapter_from_another_book_in_the_same_project_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $otherBook = Book::factory()->for($project)->create();
        $otherChapter = Chapter::factory()->for(Act::factory()->for($otherBook))->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', [
                'project' => $project,
                'q' => 'zephyrqux',
                'book' => $book->id,
                'from_chapter' => $otherChapter->id,
            ]))
            ->assertSessionHasErrors('from_chapter');
    }

    public function test_a_from_chapter_with_no_book_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', [
                'project' => $project,
                'q' => 'zephyrqux',
                'from_chapter' => $chapter->id,
            ]))
            ->assertSessionHasErrors('from_chapter');
    }

    public function test_an_unknown_domain_value_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', [
                'project' => $project,
                'q' => 'zephyrqux',
                'domains' => ['not-a-domain'],
            ]))
            ->assertSessionHasErrors('domains.0');
    }

    public function test_a_bare_search_with_no_filters_still_renders(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_a_query_wires_the_search_service_into_the_view(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'The Zephyrqux Prophecy']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $results = $response->viewData('results');
        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertFalse($results->isEmpty());
    }

    public function test_the_default_mode_is_and_when_none_is_submitted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // Matches BOTH terms — should survive AND mode.
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux Glindorf', 'description' => '']);
        // Matches only ONE term — must be excluded by AND (proves the default is
        // AllTerms, not AnyTerm).
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux Alone', 'description' => '']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux glindorf']));

        $response->assertOk();
        $this->assertSame(SearchMode::AllTerms, $response->viewData('mode'));

        $results = $response->viewData('results');
        $matchedNames = $results->plotlines
            ->map(fn ($row) => $row->entity->name)
            ->unique()
            ->values()
            ->all();

        $this->assertContains('Zephyrqux Glindorf', $matchedNames);
        $this->assertNotContains('Zephyrqux Alone', $matchedNames);
    }

    /**
     * Create a scene under a fresh chapter/act belonging to $project.
     */
    private function sceneFor(Project $project, array $attributes): Scene
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create($attributes);
    }

    public function test_a_matched_scene_renders_its_name_field_label_and_highlighted_snippet(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneFor($project, [
            'name' => 'The Opening Scene',
            'contents' => 'A fearsome zephyrqux stalks the moor.',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // Entity name (auto-escaped) and the muted field label.
        $response->assertSee('The Opening Scene');
        $response->assertSee('Contents');
        // The pre-built highlight HTML — proves the {!! !!} snippet renders raw and
        // carries the highlight role tokens the active theme paints.
        $response->assertSeeHtml('<mark class="bg-highlight text-highlight-content">zephyrqux</mark>');
        // Link points at the scene's read page.
        $response->assertSee(route('scenes.show', $scene), false);
    }

    public function test_an_unaccented_query_matches_accented_content_and_highlights_the_accented_text(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, [
            'name' => 'The Opening Scene',
            'contents' => 'A fearsome Mélusine stalks the moor.',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'Melusine']));

        $response->assertOk();
        // The unaccented query matched, and the highlighted preview keeps the
        // original accented spelling inside the <mark>.
        $response->assertSeeHtml('<mark class="bg-highlight text-highlight-content">Mélusine</mark>');
    }

    public function test_a_matched_rich_html_field_strips_tags_from_the_preview(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // Scene.notes is stored rich HTML (see RichTextFields) — the preview must show
        // the reader's plain text, not the raw <p>/<strong> markup.
        $this->sceneFor($project, [
            'name' => 'The Opening Scene',
            'notes' => '<p>A fearsome <strong>zephyrqux</strong> stalks the moor.</p>',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSeeHtml('<mark class="bg-highlight text-highlight-content">zephyrqux</mark>');
        // The raw tags never reach the page, escaped or otherwise.
        $response->assertDontSee('&lt;p&gt;', false);
        $response->assertDontSee('<p>', false);
        $response->assertDontSee('&lt;strong&gt;', false);
    }

    public function test_a_row_lists_all_matched_fields_and_carries_a_view_button(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // "zephyrqux" matches BOTH the name and the contents → the row's
        // "Matched in" cell lists both fields, concatenated with ", ".
        $scene = $this->sceneFor($project, [
            'name' => 'The zephyrqux hunt',
            'contents' => 'They tracked the zephyrqux through the marsh.',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSee('Name, Contents');
        // The trailing actions cell holds a view button pointing at the same
        // read page as the name link, so the show URL appears twice in the row.
        $response->assertSee(__('View'));
        $this->assertSame(
            2,
            substr_count($response->getContent(), route('scenes.show', $scene)),
        );
    }

    public function test_html_in_matched_content_is_not_rendered_as_markup(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, [
            'name' => 'Injection Scene',
            'contents' => 'Before <script>alert(1)</script> zephyrqux after.',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // The raw <script> tag from the source must never reach the output as live
        // markup — SearchSnippet escapes it, so only the escaped form appears.
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_entity_name_with_html_special_characters_is_escaped(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, [
            'name' => '<b>Bold</b> zephyrqux scene',
            'contents' => 'Plain body.',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // The name matched (in `name`), and it renders escaped — not as a live <b>.
        $response->assertDontSee('<b>Bold</b> zephyrqux scene', false);
        $response->assertSee('&lt;b&gt;Bold&lt;/b&gt;', false);
    }

    public function test_an_empty_query_renders_the_form_without_any_results_or_empty_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', $project));

        $response->assertOk();
        // The form is present…
        $response->assertSee('name="q"', false);
        // …but no results grouping (no result table renders) and no "no results"
        // message on the landing state. (Section words like "Timeline" live in
        // the nav, so we key off <table> — only search results emit one here.)
        $response->assertDontSee('No results match');
        $response->assertDontSee('<table', false);
    }

    public function test_a_zero_match_query_renders_a_single_page_level_empty_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'nothingmatchesthisxyz']));

        $response->assertOk();
        // Exactly one friendly page-level message — and no result tables at all
        // (not three per-section "no matches" blocks). Section words appear in the
        // nav, so we assert on <table>, which only search results emit here.
        $response->assertSee('No results match');
        $response->assertDontSee('<table', false);
    }

    public function test_only_entity_types_with_matches_render(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // One Timeline match (Plotlines) and one Story match (Scenes) — every
        // other entity type (Events, Acts, Chapters, all three Codex types) is empty.
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $content = $response->getContent();

        // Each rendered entity type is one <table>, and the search page has no
        // other tables — exactly the 2 matched types render, the 6 empty ones
        // are hidden entirely.
        $this->assertSame(2, substr_count($content, '<table'));

        // Hidden types leave no per-table empty state behind either (the
        // x-table-empty "No :items match your search or filters." row is gone).
        $response->assertDontSee('match your search');
    }

    public function test_sections_with_no_matches_are_hidden(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // Matches land only in Timeline (Plotlines) and Story (Scenes); nothing
        // in the project can produce a Codex match.
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // Each search section is one <section> element and nothing else on this
        // page emits <section> — Timeline + Story render, Codex is skipped
        // entirely (no orphaned heading over empty tables).
        $this->assertSame(2, substr_count($response->getContent(), '<section'));
    }

    public function test_a_single_matched_section_renders_alone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $content = $response->getContent();
        // Only Story renders: one section, one result table (Scenes).
        $this->assertSame(1, substr_count($content, '<section'));
        $this->assertSame(1, substr_count($content, '<table'));
    }

    public function test_an_act_chapter_and_scene_hit_each_name_their_book(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = Book::factory()->for($project)->create(['name' => 'Zephyrqux Volume']);
        $act = Act::factory()->for($book)->create(['name' => 'Zephyrqux act', 'description' => 'x']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Zephyrqux chapter', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'Zephyrqux scene', 'contents' => 'x', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // One book label per matched Act/Chapter/Scene row.
        $this->assertSame(
            3,
            substr_count($response->getContent(), '<div class="text-xs text-content-muted">Zephyrqux Volume</div>')
        );
    }

    public function test_plotline_event_and_codex_rows_render_no_book_label(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);
        Event::factory()->for($project)->create(['title' => 'Zephyrqux event', 'description' => '']);
        CodexEntry::factory()->for($project)->character()->create(['name' => 'Zephyrqux hero', 'description' => '']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // No Act/Chapter/Scene matched, so the book label never renders.
        $response->assertDontSee('<div class="text-xs text-content-muted">', false);
    }

    public function test_codex_result_links_to_its_show_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Zephyrqux the Bold']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSee('Zephyrqux the Bold');
        $response->assertSee(route('codex.show', $entry), false);
    }

    public function test_a_scene_result_links_to_its_show_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSee(route('scenes.show', $scene), false);
    }

    public function test_mode_control_is_a_fieldset_and_section_headings_are_h2(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // Keyboard-accessible mode control (fieldset over the radio group).
        $response->assertSee('<fieldset', false);
        // Section headings are real <h2>s.
        $response->assertSee('<h2', false);
    }

    public function test_a_domain_page_shows_that_domains_matches(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);
        // Different domain — proves the domain page shows only its own column.
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', ['project' => $project, 'domain' => 'plotlines', 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSee('Zephyrqux arc');
        $response->assertDontSee('Zephyrqux scene');
    }

    public function test_a_domain_page_names_the_book_on_an_act_hit(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = Book::factory()->for($project)->create(['name' => 'Zephyrqux Volume']);
        Act::factory()->for($book)->create(['name' => 'Zephyrqux act', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', ['project' => $project, 'domain' => 'acts', 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSeeHtml('<div class="text-xs text-content-muted">Zephyrqux Volume</div>');
    }

    public function test_a_domain_page_second_page_slices_correctly_and_carries_q_and_mode(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        Event::factory()->for($project)->count(60)->sequence(
            fn ($sequence) => ['title' => "Zephyrqux {$sequence->index}", 'description' => '']
        )->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'events',
                'q' => 'zephyrqux',
                'mode' => 'any',
                'page' => 2,
            ]));

        $response->assertOk();
        $paginator = $response->viewData('paginator');
        $this->assertSame(60, $paginator->total());
        $this->assertCount(10, $paginator);
        // Page links carry q/mode onward.
        $response->assertSee('q=zephyrqux', false);
        $response->assertSee('mode=any', false);
    }

    /** The "see all" page honours the same rows-per-page as every entity list. */
    public function test_a_domain_page_uses_the_readers_rows_per_page(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        Event::factory()->for($project)->count(60)->sequence(
            fn ($sequence) => ['title' => "Zephyrqux {$sequence->index}", 'description' => '']
        )->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'events',
                'q' => 'zephyrqux',
                'mode' => 'any',
            ]));

        $response->assertOk();
        $this->assertSame(50, $response->viewData('paginator')->perPage());
    }

    /** A reader who has never chosen one gets the configured default, not a search setting. */
    public function test_a_domain_page_falls_back_to_the_default_rows_per_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'plotlines',
                'q' => 'zephyrqux',
            ]));

        $response->assertOk();
        $this->assertSame(config('pagination.default'), $response->viewData('paginator')->perPage());
    }

    public function test_a_domain_page_overshoot_page_shows_an_empty_state_not_a_500(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux arc', 'description' => '']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'plotlines',
                'q' => 'zephyrqux',
                'page' => 99,
            ]));

        $response->assertOk();
        $response->assertSee('No more results');
    }

    public function test_a_non_owner_cannot_view_a_domain_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.search.domain', ['project' => $project, 'domain' => 'plotlines', 'q' => 'zephyrqux']))
            ->assertForbidden();
    }

    public function test_an_unknown_domain_404s(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.search.index', $project).'/not-a-domain?q=zephyrqux')
            ->assertNotFound();
    }

    public function test_a_blank_query_on_a_domain_page_redirects_to_the_main_search_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', ['project' => $project, 'domain' => 'plotlines']));

        $response->assertRedirect(route('projects.search.index', ['project' => $project, 'mode' => 'all']));
    }

    public function test_a_column_over_the_cap_shows_only_capped_rows_and_a_see_all_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $cap = config('search.cap');
        Plotline::factory()->for($project)->count($cap + 3)->sequence(
            fn ($sequence) => ['name' => "Zephyrqux {$sequence->index}", 'description' => '']
        )->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'mode' => 'any']));

        $response->assertOk();
        $content = $response->getContent();

        // Exactly `cap` rows render for the Plotlines column.
        $this->assertSame($cap, preg_match_all('/Zephyrqux \d+/', $content));

        $expectedHref = route('projects.search.domain', [
            'project' => $project,
            'domain' => 'plotlines',
            'q' => 'zephyrqux',
            'mode' => 'any',
        ]);
        $response->assertSee(e($expectedHref), false);
        // N in the link text is the true total, not the capped count.
        $response->assertSee(__('See all :count results', ['count' => $cap + 3]));
    }

    public function test_a_column_at_or_under_the_cap_shows_every_row_and_no_see_all_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $cap = config('search.cap');
        Plotline::factory()->for($project)->count($cap)->sequence(
            fn ($sequence) => ['name' => "Zephyrqux {$sequence->index}", 'description' => '']
        )->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertSame($cap, preg_match_all('/Zephyrqux \d+/', $content));
        $response->assertDontSee('See all');
    }

    public function test_capping_one_column_does_not_truncate_a_smaller_sibling_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $cap = config('search.cap');
        // Plotlines exceeds the cap; Events stays under it.
        Plotline::factory()->for($project)->count($cap + 2)->sequence(
            fn ($sequence) => ['name' => "Zephyrqux plot {$sequence->index}", 'description' => '']
        )->create();
        Event::factory()->for($project)->create(['title' => 'Zephyrqux event', 'description' => '']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        // The small Events column is untouched by the Plotlines cap.
        $response->assertSee('Zephyrqux event');
    }

    public function test_filters_round_trip_through_the_url(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $this->sceneFor($project, ['name' => 'plain', 'contents' => 'zephyrqux', 'description' => 'x']);
        Plotline::factory()->for($project)->create(['name' => 'zephyrqux plot', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', [
                'project' => $project,
                'q' => 'zephyrqux',
                'book' => $book->id,
                'domains' => ['scenes'],
            ]));

        $response->assertOk();

        $scope = $response->viewData('scope');
        $this->assertSame($book->id, $scope->bookId);
        $this->assertSame([SearchDomain::Scenes], $scope->domains);

        $results = $response->viewData('results');
        $this->assertTrue($results->scenes->isNotEmpty());
        // The book filter makes Plotlines meaningless, so it never runs.
        $this->assertTrue($results->plotlines->isEmpty());
    }

    public function test_see_all_link_carries_book_range_and_domains(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $cap = config('search.cap');
        Scene::factory()->for($chapter)->count($cap + 2)->sequence(
            fn ($sequence) => ['name' => "Zephyrqux {$sequence->index}", 'contents' => 'x', 'description' => 'x']
        )->create();

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', [
                'project' => $project,
                'q' => 'zephyrqux',
                'book' => $book->id,
                'from_chapter' => $chapter->id,
                'to_chapter' => $chapter->id,
                'domains' => ['scenes'],
            ]));

        $response->assertOk();

        $expectedHref = route('projects.search.domain', [
            'project' => $project,
            'domain' => 'scenes',
            'q' => 'zephyrqux',
            'mode' => 'all',
            'book' => $book->id,
            'from_chapter' => $chapter->id,
            'to_chapter' => $chapter->id,
            'domains' => ['scenes'],
        ]);
        $response->assertSee(e($expectedHref), false);
    }

    public function test_the_domain_page_honours_book_and_range_across_page_2(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        Scene::factory()->for($chapter)->count(60)->sequence(
            fn ($sequence) => ['name' => "Zephyrqux {$sequence->index}", 'contents' => 'x', 'description' => 'x']
        )->create();
        // A different book's match must be excluded by the book filter.
        $otherBook = Book::factory()->for($project)->create();
        $otherAct = Act::factory()->for($otherBook)->create();
        $otherChapter = Chapter::factory()->for($otherAct)->create();
        Scene::factory()->for($otherChapter)->create(['name' => 'Zephyrqux other', 'contents' => 'x', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'scenes',
                'q' => 'zephyrqux',
                'mode' => 'any',
                'book' => $book->id,
                'from_chapter' => $chapter->id,
                'to_chapter' => $chapter->id,
                'page' => 2,
            ]));

        $response->assertOk();
        $paginator = $response->viewData('paginator');
        $this->assertSame(60, $paginator->total());
        $this->assertCount(10, $paginator);
        // Page links carry the book and range onward.
        $response->assertSee("book={$book->id}", false);
        $response->assertSee("from_chapter={$chapter->id}", false);
        $response->assertSee("to_chapter={$chapter->id}", false);
    }

    public function test_a_book_filter_plus_a_project_wide_domain_page_redirects_to_the_index(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux plot', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'plotlines',
                'q' => 'zephyrqux',
                'book' => $book->id,
            ]));

        $response->assertRedirect(route('projects.search.index', [
            'project' => $project,
            'q' => 'zephyrqux',
            'mode' => 'all',
            'book' => $book->id,
        ]));
    }

    public function test_an_unchecked_domains_own_page_still_renders(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux plot', 'description' => 'x']);

        // domains[] names only Events; the URL still asks for the Plotlines page directly.
        $response = $this->actingAs($user)
            ->get(route('projects.search.domain', [
                'project' => $project,
                'domain' => 'plotlines',
                'q' => 'zephyrqux',
                'domains' => ['events'],
            ]));

        $response->assertOk();
        $response->assertSee('Zephyrqux plot');
    }

    public function test_a_multi_book_project_with_no_book_chosen_loads_no_chapters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Book::factory()->for($project)->create();

        DB::connection()->enableQueryLog();

        $response = $this->actingAs($user)->get(route('projects.search.index', $project));

        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        $response->assertOk();
        $this->assertTrue($response->viewData('chapters')->isEmpty());

        foreach ($queries as $executed) {
            $this->assertStringNotContainsStringIgnoringCase('chapters', $executed['query']);
        }
    }

    public function test_a_single_book_project_renders_no_book_select(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();

        $response = $this->actingAs($user)->get(route('projects.search.index', $project));

        $response->assertOk();
        $response->assertDontSee('id="narrow-book"', false);
        $response->assertSee('name="book" value="'.$book->id.'"', false);
    }

    public function test_a_multi_book_project_with_no_book_chosen_renders_the_note_not_the_range(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Book::factory()->for($project)->create();

        $response = $this->actingAs($user)->get(route('projects.search.index', $project));

        $response->assertOk();
        $response->assertSee('id="narrow-book"', false);
        $response->assertSee('Choose a book first to narrow by chapter range.');
        $response->assertDontSee('id="narrow-from-chapter"', false);
        $response->assertDontSee('id="narrow-to-chapter"', false);
    }

    public function test_a_chosen_book_lists_its_chapters_in_story_order(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();

        $laterAct = Act::factory()->for($book)->create(['position' => 2]);
        $earlierAct = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($laterAct)->create(['name' => 'Zephyrqux second chapter', 'position' => 1]);
        Chapter::factory()->for($earlierAct)->create(['name' => 'Zephyrqux first chapter', 'position' => 1]);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'book' => $book->id]));

        $response->assertOk();
        $response->assertSeeInOrder([
            '1. Zephyrqux first chapter',
            '2. Zephyrqux second chapter',
        ]);
    }

    public function test_submitted_filters_come_back_selected_and_the_panel_renders_open(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Zephyrqux chapter']);

        $response = $this->actingAs($user)->get(route('projects.search.index', [
            'project' => $project,
            'q' => 'zephyrqux',
            'book' => $book->id,
            'from_chapter' => $chapter->id,
            'to_chapter' => $chapter->id,
            'domains' => [SearchDomain::Scenes->value],
        ]));

        $response->assertOk();
        $response->assertSee('<details  open', false);
        $response->assertSee('value="'.$chapter->id.'" selected', false);
        $response->assertSee('value="scenes"', false);
        $this->assertMatchesRegularExpression(
            '/value="scenes"\s+checked/',
            $response->getContent()
        );
    }

    public function test_an_unnamed_book_shows_the_project_name_never_the_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Zephyrqux Project']);
        $project->books()->first()->update(['name' => null]);
        Book::factory()->for($project)->create();

        $response = $this->actingAs($user)->get(route('projects.search.index', $project));

        $response->assertOk();
        $response->assertSee('Zephyrqux Project');
        $response->assertDontSee('#'.$project->books()->first()->id);
    }

    public function test_a_book_filter_hides_timeline_and_codex_with_the_explanatory_line(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x', 'description' => 'x']);
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux plot', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'book' => $book->id]));

        $response->assertOk();
        $response->assertSee('Zephyrqux scene');
        $response->assertDontSee('Zephyrqux plot');
        $response->assertSee('Plotlines, events and the codex belong to the whole project. Clear the book filter to search them.');
    }

    public function test_unchecking_a_domain_renders_no_table_and_no_explanatory_line(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x', 'description' => 'x']);
        Plotline::factory()->for($project)->create(['name' => 'Zephyrqux plot', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'domains' => ['scenes']]));

        $response->assertOk();
        $response->assertSee('Zephyrqux scene');
        $response->assertDontSee('Zephyrqux plot');
        $response->assertDontSee('Plotlines, events and the codex belong to the whole project.');
    }

    public function test_the_filter_summary_names_an_active_book_filter_and_clear_drops_every_filter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first()->fresh();
        $book->update(['name' => 'Zephyrqux Volume']);
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'book' => $book->id]));

        $response->assertOk();
        $response->assertSee('Filtered to Zephyrqux Volume.');
        $response->assertSee(e(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux', 'mode' => 'all'])), false);
    }

    public function test_an_unfiltered_search_renders_no_summary(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->sceneFor($project, ['name' => 'Zephyrqux scene', 'contents' => 'x', 'description' => 'x']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertDontSee('Filtered to');
    }
}
