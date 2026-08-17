<?php

namespace Tests\Feature;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\ProjectSearch;
use App\Support\RichText;
use App\Support\SearchResultRow;
use App\Support\SearchResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the ProjectSearch service directly against the DB (feature-style, like
 * AttributeTimelineTest) — six per-entity queries, three modes, LIKE-wildcard
 * escaping, one row per matched entity (listing every matched field),
 * cross-project isolation, ordering, and no N+1.
 */
class ProjectSearchTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::factory()->for(User::factory())->create();
    }

    private function search(Project $project, string $query, SearchMode $mode = SearchMode::AllTerms): SearchResults
    {
        return app(ProjectSearch::class)->search($project, $query, $mode);
    }

    /**
     * Build a chapter belonging to a fresh act under the given project, so scenes
     * can be scoped through the chapter.act.project chain.
     */
    private function chapterIn(Project $project): Chapter
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create(['name' => 'plain act', 'description' => 'plain']);

        return Chapter::factory()->for($act)->create(['name' => 'plain chapter', 'description' => 'plain']);
    }

    public function test_each_entity_type_is_returned_in_the_right_column_with_the_right_field_label(): void
    {
        $project = $this->project();
        $book = $project->books()->first();
        $chapter = $this->chapterIn($project);

        Plotline::factory()->for($project)->create(['name' => 'quibble plotline', 'description' => 'x']);
        Event::factory()->for($project)->create(['title' => 'quibble event', 'description' => 'x']);
        $act = Act::factory()->for($book)->create(['name' => 'quibble act', 'description' => 'x']);
        Chapter::factory()->for($act)->create(['name' => 'quibble chapter', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'the quibble scene', 'description' => 'x']);
        CodexEntry::factory()->for($project)->character()->create(['name' => 'quibble hero', 'description' => 'x']);

        $results = $this->search($project, 'quibble');

        $this->assertCount(1, $results->plotlines);
        $this->assertSame(['Name'], $results->plotlines->first()->fieldLabels);

        $this->assertCount(1, $results->events);
        $this->assertSame(['Title'], $results->events->first()->fieldLabels);

        $this->assertCount(1, $results->acts);
        $this->assertSame(['Name'], $results->acts->first()->fieldLabels);

        $this->assertCount(1, $results->chapters);
        $this->assertSame(['Name'], $results->chapters->first()->fieldLabels);

        $this->assertCount(1, $results->scenes);
        $this->assertSame(['Contents'], $results->scenes->first()->fieldLabels);

        $this->assertCount(1, $results->characters);
        $this->assertSame(['Name'], $results->characters->first()->fieldLabels);
        $this->assertCount(0, $results->locations);
        $this->assertCount(0, $results->organizations);
    }

    public function test_codex_entries_split_into_one_column_per_type(): void
    {
        $project = $this->project();

        CodexEntry::factory()->for($project)->character()->create(['name' => 'zephyr hero', 'description' => 'x']);
        CodexEntry::factory()->for($project)->location()->create(['name' => 'zephyr city', 'description' => 'x']);
        CodexEntry::factory()->for($project)->organization()->create(['name' => 'zephyr guild', 'description' => 'x']);

        $results = $this->search($project, 'zephyr');

        $this->assertCount(1, $results->characters);
        $this->assertCount(1, $results->locations);
        $this->assertCount(1, $results->organizations);
    }

    public function test_and_mode_matches_terms_across_different_fields(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'here lurks the moonglaive',
            'notes' => '<p>and also the sunspear</p>',
            'description' => 'x',
        ]);

        $results = $this->search($project, 'moonglaive sunspear', SearchMode::AllTerms);

        $this->assertCount(1, $results->scenes, 'one row per matched entity');
        $this->assertSame(['Contents', 'Notes'], $results->scenes->first()->fieldLabels);
    }

    public function test_and_mode_does_not_match_when_a_term_is_absent(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'here lurks the moonglaive',
            'notes' => null,
            'description' => 'x',
        ]);

        $results = $this->search($project, 'moonglaive sunspear', SearchMode::AllTerms);

        $this->assertTrue($results->isEmpty());
    }

    public function test_or_mode_is_a_superset_of_and_mode(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'only moonglaive here', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'only sunspear here', 'description' => 'x']);

        $and = $this->search($project, 'moonglaive sunspear', SearchMode::AllTerms);
        $or = $this->search($project, 'moonglaive sunspear', SearchMode::AnyTerm);

        $this->assertCount(0, $and->scenes);
        $this->assertCount(2, $or->scenes);
    }

    public function test_exact_phrase_is_word_order_sensitive_and_single_field(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        $match = Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'the red dragon flies', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'the dragon is red', 'description' => 'x']);
        // Words present but split across two fields must not satisfy an exact phrase.
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'a red thing', 'notes' => '<p>a dragon</p>', 'description' => 'x']);

        $results = $this->search($project, 'red dragon', SearchMode::ExactPhrase);

        $this->assertCount(1, $results->scenes);
        $this->assertSame($match->id, $results->scenes->first()->entity->id);
    }

    /**
     * A rich-HTML field is flattened by {@see RichText::toPlainText()},
     * which breaks on every block boundary. The preview must therefore show
     * "Chapter One / She waited", never the glued "Chapter OneShe waited" it used to.
     */
    public function test_a_snippet_spanning_a_block_boundary_keeps_the_words_apart(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'plain',
            'notes' => '<h2>Chapter One</h2><p>She waited.</p>',
            'description' => 'x',
        ]);

        $snippet = $this->search($project, 'waited')->scenes->first()->snippet;

        $this->assertStringContainsString('<mark', $snippet);
        $this->assertStringContainsString('Chapter One', $snippet);
        $this->assertStringNotContainsString('OneShe', $snippet, 'the heading must not glue onto the paragraph');
    }

    /**
     * Deliberate, asserted both ways: the boundary separator is a newline, and
     * AccentFolder::fold() does not normalise whitespace, so an exact phrase still
     * cannot span two blocks. That is the intent — a phrase the writer never wrote
     * on one line is not a phrase match — and the fix does not silently change it.
     */
    public function test_an_exact_phrase_does_not_match_across_a_block_boundary(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'plain',
            'notes' => '<h2>Chapter One</h2><p>She waited.</p>',
            'description' => 'x',
        ]);

        // Within one block: matches.
        $this->assertCount(1, $this->search($project, 'Chapter One', SearchMode::ExactPhrase)->scenes);

        // Across the heading → paragraph boundary: no match, in any spelling.
        $this->assertCount(0, $this->search($project, 'One She', SearchMode::ExactPhrase)->scenes);
        $this->assertCount(0, $this->search($project, 'OneShe', SearchMode::ExactPhrase)->scenes);
    }

    public function test_like_wildcards_in_a_term_are_escaped_and_match_literally(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        $percent = Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'saved 50% today', 'description' => 'x']);
        // Contains "50" but NOT "50%": an unescaped %50%% pattern would wrongly match this.
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'the year 1950 was calm', 'description' => 'x']);

        $underscore = Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'file a_b here', 'description' => 'x']);
        // Would be matched by an unescaped a_b (underscore = any single char).
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'contents' => 'value axb here', 'description' => 'x']);

        $percentResults = $this->search($project, '50%', SearchMode::ExactPhrase);
        $this->assertCount(1, $percentResults->scenes);
        $this->assertSame($percent->id, $percentResults->scenes->first()->entity->id);

        $underscoreResults = $this->search($project, 'a_b', SearchMode::ExactPhrase);
        $this->assertCount(1, $underscoreResults->scenes);
        $this->assertSame($underscore->id, $underscoreResults->scenes->first()->entity->id);
    }

    public function test_a_scene_matching_two_fields_yields_one_row_listing_both_fields(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'the glimmerstone glows',
            'notes' => '<p>notes about the glimmerstone</p>',
            'description' => 'x',
        ]);

        $results = $this->search($project, 'glimmerstone');

        // One row per matched entity; every matched field is listed, in the
        // declared field order (contents before notes).
        $this->assertCount(1, $results->scenes);
        $row = $results->scenes->first();
        $this->assertSame(['Contents', 'Notes'], $row->fieldLabels);
        $this->assertSame('Contents, Notes', $row->matchedFields());
        // The text preview comes from the FIRST matching field (contents here).
        $this->assertStringContainsString('<mark', $row->snippet);
        $this->assertStringContainsString('glows', $row->snippet);
    }

    public function test_results_are_isolated_to_the_searched_project(): void
    {
        $projectOne = $this->project();
        $projectTwo = $this->project();

        $mine = Plotline::factory()->for($projectOne)->create(['name' => 'isolate mine', 'description' => 'x']);
        Plotline::factory()->for($projectTwo)->create(['name' => 'isolate theirs', 'description' => 'x']);

        // Also seed a second project's scene through the parent chain to prove the
        // chapter.act.project scoping isolates too.
        $otherChapter = $this->chapterIn($projectTwo);
        Scene::factory()->for($otherChapter)->create(['name' => 'plain', 'contents' => 'isolate scene', 'description' => 'x']);

        $results = $this->search($projectOne, 'isolate');

        $this->assertCount(1, $results->plotlines);
        $this->assertSame($mine->id, $results->plotlines->first()->entity->id);
        $this->assertCount(0, $results->scenes);
    }

    public function test_scenes_are_returned_in_position_order(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        // Insert out of position order to prove ordering is by `position`, not insertion.
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 3, 'contents' => 'orderterm gamma', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 1, 'contents' => 'orderterm alpha', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 2, 'contents' => 'orderterm beta', 'description' => 'x']);

        $results = $this->search($project, 'orderterm');

        $positions = $results->scenes->map(fn (SearchResultRow $row) => $row->entity->position)->all();
        $this->assertSame([1, 2, 3], $positions);
    }

    public function test_search_runs_a_fixed_number_of_queries_regardless_of_match_count(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        // Many matching rows across several entity types.
        Plotline::factory()->count(3)->for($project)->create(['description' => 'countword']);
        Event::factory()->count(3)->for($project)->create(['description' => 'countword']);
        Scene::factory()->count(5)->for($chapter)->create(['name' => 'plain', 'contents' => 'countword', 'description' => 'x']);
        CodexEntry::factory()->count(3)->for($project)->character()->create(['description' => 'countword']);

        DB::connection()->enableQueryLog();

        $results = $this->search($project, 'countword');

        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        // Exactly one query per searchable entity type — no N+1 across matched rows.
        $this->assertCount(6, $queries);
        $this->assertGreaterThanOrEqual(14, $results->count());
    }

    public function test_search_matches_raw_markdown_and_html_not_rendered_output(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'a **dragonfly** appeared',   // term sits inside Markdown emphasis
            'notes' => '<p>the <em>ghostwind</em> howled</p>', // term sits inside raw HTML
            'description' => 'x',
        ]);

        $markdown = $this->search($project, 'dragonfly');
        $this->assertCount(1, $markdown->scenes);
        $this->assertSame(['Contents'], $markdown->scenes->first()->fieldLabels);

        $html = $this->search($project, 'ghostwind');
        $this->assertCount(1, $html->scenes);
        $this->assertSame(['Notes'], $html->scenes->first()->fieldLabels);
    }

    public function test_an_unaccented_term_matches_accented_data(): void
    {
        $project = $this->project();

        $character = CodexEntry::factory()->for($project)->character()
            ->create(['name' => 'Mélusine', 'description' => 'x']);

        $results = $this->search($project, 'Melusine');

        $this->assertCount(1, $results->characters);
        $this->assertSame($character->id, $results->characters->first()->entity->id);
        $this->assertSame(['Name'], $results->characters->first()->fieldLabels);
    }

    public function test_an_accented_term_matches_unaccented_data(): void
    {
        $project = $this->project();

        $character = CodexEntry::factory()->for($project)->character()
            ->create(['name' => 'Melusine', 'description' => 'x']);

        $results = $this->search($project, 'Mélusine');

        $this->assertCount(1, $results->characters);
        $this->assertSame($character->id, $results->characters->first()->entity->id);
    }

    public function test_accent_folding_applies_to_non_name_fields_too(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        // Accented term sits in the scene contents, not the name.
        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'they crossed the forêt at dusk',
            'description' => 'x',
        ]);

        $results = $this->search($project, 'foret');

        $this->assertCount(1, $results->scenes);
        $this->assertSame(['Contents'], $results->scenes->first()->fieldLabels);
    }

    /**
     * Matching and previewing read the same fields but need different text:
     * comparison happens on accent-folded, lowercased values, while the preview
     * must show what the writer actually typed. The two now travel side by side
     * through the matcher, so handing the wrong one to the snippet builder is a
     * one-word mistake — and one no other test would catch, since every accent
     * test above asserts only that the row came back.
     */
    public function test_the_preview_shows_the_writers_own_text_not_the_folded_match_text(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create([
            'name' => 'plain',
            'contents' => 'They crossed the FORÊT at dusk',
            'description' => 'x',
        ]);

        $snippet = $this->search($project, 'foret')->scenes->first()->snippet;

        $this->assertStringContainsString('<mark', $snippet);
        $this->assertStringContainsString('FORÊT', $snippet, 'the preview must keep the stored accents and casing');
        $this->assertStringNotContainsString('foret at dusk', $snippet, 'the preview must not be the folded text');
    }

    public function test_blank_query_returns_an_empty_result_set(): void
    {
        $project = $this->project();
        Plotline::factory()->for($project)->create(['name' => 'anything']);

        $results = $this->search($project, '   ');

        $this->assertTrue($results->isEmpty());
    }

    public function test_search_domain_returns_only_that_domains_matches(): void
    {
        $project = $this->project();

        Plotline::factory()->for($project)->create(['name' => 'domainword plotline', 'description' => 'x']);
        Event::factory()->for($project)->create(['title' => 'domainword event', 'description' => 'x']);

        $rows = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Plotlines, 'domainword', SearchMode::AllTerms);

        $this->assertCount(1, $rows);
        $this->assertSame(['Name'], $rows->first()->fieldLabels);
    }

    public function test_search_domain_codex_split_excludes_other_types(): void
    {
        $project = $this->project();

        CodexEntry::factory()->for($project)->character()->create(['name' => 'splitword hero', 'description' => 'x']);
        CodexEntry::factory()->for($project)->location()->create(['name' => 'splitword city', 'description' => 'x']);
        CodexEntry::factory()->for($project)->organization()->create(['name' => 'splitword guild', 'description' => 'x']);

        $characters = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Characters, 'splitword', SearchMode::AllTerms);
        $locations = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Locations, 'splitword', SearchMode::AllTerms);
        $organizations = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Organizations, 'splitword', SearchMode::AllTerms);

        $this->assertCount(1, $characters);
        $this->assertCount(1, $locations);
        $this->assertCount(1, $organizations);
    }

    public function test_search_domain_preserves_natural_order(): void
    {
        $project = $this->project();
        $chapter = $this->chapterIn($project);

        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 3, 'contents' => 'domainorder gamma', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 1, 'contents' => 'domainorder alpha', 'description' => 'x']);
        Scene::factory()->for($chapter)->create(['name' => 'plain', 'position' => 2, 'contents' => 'domainorder beta', 'description' => 'x']);

        $rows = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Scenes, 'domainorder', SearchMode::AllTerms);

        $positions = $rows->map(fn (SearchResultRow $row) => $row->entity->position)->all();
        $this->assertSame([1, 2, 3], $positions);
    }

    public function test_search_domain_returns_empty_collection_for_blank_query(): void
    {
        $project = $this->project();
        Plotline::factory()->for($project)->create(['name' => 'anything']);

        $rows = app(ProjectSearch::class)->searchDomain($project, SearchDomain::Plotlines, '   ', SearchMode::AllTerms);

        $this->assertTrue($rows->isEmpty());
    }

    public function test_search_config_keys_load(): void
    {
        $this->assertSame(5, config('search.cap'));
        $this->assertSame(20, config('search.per_page'));
    }
}
