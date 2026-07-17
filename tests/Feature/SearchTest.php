<?php

namespace Tests\Feature;

use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\SearchResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $act = Act::factory()->for($project)->create();
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
        // carries the bg-sun-200 class the CSS is built to style.
        $response->assertSeeHtml('<mark class="bg-sun-200">zephyrqux</mark>');
        // Link points at the scene's existing edit page.
        $response->assertSee(route('scenes.edit', $scene), false);
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
        $response->assertSeeHtml('<mark class="bg-sun-200">Mélusine</mark>');
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
        $response->assertSeeHtml('<mark class="bg-sun-200">zephyrqux</mark>');
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
        // edit page as the name link (entities have no separate show page), so
        // the edit URL appears twice in the row.
        $response->assertSee(__('View'));
        $this->assertSame(
            2,
            substr_count($response->getContent(), route('scenes.edit', $scene)),
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

    public function test_codex_result_links_to_its_edit_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Zephyrqux the Bold']);

        $response = $this->actingAs($user)
            ->get(route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux']));

        $response->assertOk();
        $response->assertSee('Zephyrqux the Bold');
        $response->assertSee(route('codex.edit', $entry), false);
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
}
