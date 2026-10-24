<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\SceneFindReplace;
use App\Support\FieldHash;
use App\Support\FindReplacePreview;
use App\Support\FindReplaceQuery;
use App\Support\SearchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The find and replace page (route + FindReplaceController + FindReplacePreviewRequest)
 * and the preview that SceneFindReplace builds. Matching itself is covered by
 * SceneTextMatcherTest.
 */
class FindReplacePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        [$this->project, $this->book] = $this->projectWithBook($this->user);
    }

    private function chapterIn(Book $book, array $act = [], array $chapter = []): Chapter
    {
        $act = Act::factory()->for($book)->create($act);

        return Chapter::factory()->for($act)->create($chapter);
    }

    private function sceneIn(Chapter $chapter, string $contents, array $attributes = []): Scene
    {
        return Scene::factory()->for($chapter)->create(['contents' => $contents] + $attributes);
    }

    private function preview(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->get(route('projects.replace.index', ['project' => $this->project] + $query));
    }

    private function previewData(TestResponse $response): FindReplacePreview
    {
        return $response->viewData('preview');
    }

    public function test_a_non_owner_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.replace.index', ['project' => $this->project, 'find' => 'x']))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('projects.replace.index', $this->project))
            ->assertRedirect(route('login'));
    }

    public function test_a_blank_find_shows_the_form_and_runs_no_scene_query(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'Lane');

        DB::enableQueryLog();
        $response = $this->preview(['find' => '', 'replace' => 'x']);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

        $response->assertOk()->assertSessionHasNoErrors();
        $this->assertNull($response->viewData('preview'));
        $this->assertStringNotContainsString('from "scenes"', $queries);
        $response->assertSee('name="find"', false);
    }

    public function test_a_missing_find_is_the_landing_state(): void
    {
        $response = $this->preview();

        $response->assertOk();
        $this->assertNull($response->viewData('preview'));
    }

    public function test_the_owner_sees_matches_grouped_by_scene_in_manuscript_order(): void
    {
        $second = Book::factory()->for($this->project)->create(['name' => 'Second book']);

        // Created in the opposite order of the manuscript, so ID order is wrong.
        $lateChapter = $this->chapterIn($second, ['name' => 'Late act'], ['name' => 'Late chapter']);
        $lateScene = $this->sceneIn($lateChapter, 'Lane at the end', ['name' => 'Last']);

        $firstAct = Act::factory()->for($this->book)->create(['name' => 'Act B', 'position' => 2]);
        $laterChapter = Chapter::factory()->for($firstAct)->create(['name' => 'Chapter B']);
        $laterScene = $this->sceneIn($laterChapter, 'Lane in act B', ['name' => 'Act B scene']);

        $earlyAct = Act::factory()->for($this->book)->create(['name' => 'Act A', 'position' => 1]);
        $earlyChapter = Chapter::factory()->for($earlyAct)->create(['name' => 'Chapter A']);
        $secondInChapter = $this->sceneIn($earlyChapter, 'Lane twice, Lane', ['name' => 'A two', 'position' => 2]);
        $firstInChapter = $this->sceneIn($earlyChapter, 'Lane once', ['name' => 'A one', 'position' => 1]);

        $this->sceneIn($earlyChapter, 'nothing here', ['name' => 'No match']);

        $response = $this->preview(['find' => 'Lane']);

        $response->assertOk()->assertSee('Act A / Chapter A /', false);
        $preview = $this->previewData($response);

        $this->assertSame(
            [$firstInChapter->id, $secondInChapter->id, $laterScene->id, $lateScene->id],
            array_map(fn ($group) => $group->scene->id, $preview->scenes),
        );
        $this->assertSame(5, $preview->matchCount());
        $this->assertFalse($preview->capped);
        $response->assertSee('data-match-count="5"', false);
    }

    public function test_each_scene_carries_the_hash_of_its_contents_and_each_match_its_index(): void
    {
        $scene = $this->sceneIn($this->chapterIn($this->book), 'Lane and Lane and Lane');

        $group = $this->previewData($this->preview(['find' => 'Lane']))->scenes[0];

        $this->assertSame(FieldHash::of($scene->contents), $group->hash);
        $this->assertSame([0, 1, 2], array_map(fn ($match) => $match->index, $group->matches));
        $this->assertSame([0, 9, 18], array_map(fn ($match) => $match->offset, $group->matches));
    }

    public function test_the_context_is_plain_escaped_text_with_the_old_and_new_text(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'She met <b>*Lane*</b> there');

        $this->preview(['find' => 'Lane', 'replace' => 'Dane & Co', 'whole_word' => 1])
            ->assertOk()
            ->assertSee('She met &lt;b&gt;*<del', false)
            ->assertSee('>Lane</del><ins', false)
            ->assertSee('>Dane &amp; Co</ins>*&lt;/b&gt; there', false)
            ->assertDontSee('<b>*', false);
    }

    public function test_the_context_is_about_sixty_characters_each_side_with_ellipses(): void
    {
        $before = str_repeat('é', 200);
        $after = str_repeat('à', 100);
        $this->sceneIn($this->chapterIn($this->book), $before.' Lane '.$after);

        $match = $this->previewData($this->preview(['find' => 'Lane']))->scenes[0]->matches[0];

        $this->assertSame("\u{2026}".str_repeat('é', 59).' ', $match->before);
        $this->assertSame(' '.str_repeat('à', 59)."\u{2026}", $match->after);
        $this->assertSame('Lane', $match->found);
        $this->assertStringEndsWith("\u{2026}", $match->after);
    }

    public function test_a_short_scene_has_no_ellipsis(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'Meet Lane now');

        $match = $this->previewData($this->preview(['find' => 'Lane']))->scenes[0]->matches[0];

        $this->assertSame('Meet ', $match->before);
        $this->assertSame(' now', $match->after);
    }

    public function test_line_breaks_in_the_context_become_spaces(): void
    {
        $this->sceneIn($this->chapterIn($this->book), "first line\n\nLane\nlast line");

        $match = $this->previewData($this->preview(['find' => 'Lane']))->scenes[0]->matches[0];

        $this->assertSame('first line ', $match->before);
        $this->assertSame(' last line', $match->after);
    }

    public function test_match_case_and_whole_word_default_on_and_the_form_can_switch_them_off(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'lane Lane Laneway');

        $default = $this->previewData($this->preview(['find' => 'Lane']));
        $this->assertSame(1, $default->matchCount());

        $loose = $this->previewData($this->preview(['find' => 'Lane', 'match_case' => '0', 'whole_word' => '0']));
        $this->assertSame(3, $loose->matchCount());
    }

    public function test_the_form_keeps_what_the_author_typed(): void
    {
        $this->preview(['find' => 'Lane', 'replace' => 'Dane', 'match_case' => '0'])
            ->assertSee('value="Lane"', false)
            ->assertSee('value="Dane"', false);
    }

    public function test_spaces_in_the_find_text_are_kept(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'one  two   three');

        $preview = $this->previewData($this->preview(['find' => '  ', 'whole_word' => '0']));

        $this->assertSame(2, $preview->matchCount());
    }

    public function test_scenes_of_another_project_are_never_listed(): void
    {
        [, $otherBook] = $this->projectWithBook();
        $this->sceneIn($this->chapterIn($otherBook), 'Lane elsewhere');

        $response = $this->preview(['find' => 'Lane']);

        $this->assertTrue($this->previewData($response)->isEmpty());
        $response->assertSee(__('No matches for “:find”.', ['find' => 'Lane']));
    }

    public function test_an_empty_result_names_the_scope(): void
    {
        $this->book->update(['name' => 'Book One']);

        $this->preview(['find' => 'nothing', 'book' => $this->book->id])
            ->assertSee(__('Filtered to :summary.', ['summary' => 'Book One']));
    }

    public function test_the_book_scope_excludes_scenes_of_other_books(): void
    {
        $second = Book::factory()->for($this->project)->create();
        $inBook = $this->sceneIn($this->chapterIn($this->book), 'Lane');
        $this->sceneIn($this->chapterIn($second), 'Lane');

        $preview = $this->previewData($this->preview(['find' => 'Lane', 'book' => $this->book->id]));

        $this->assertSame([$inBook->id], array_map(fn ($group) => $group->scene->id, $preview->scenes));
    }

    public function test_the_act_scope_excludes_scenes_of_other_acts(): void
    {
        $chapterA = $this->chapterIn($this->book);
        $chapterB = $this->chapterIn($this->book);
        $inAct = $this->sceneIn($chapterA, 'Lane');
        $this->sceneIn($chapterB, 'Lane');

        $preview = $this->previewData($this->preview([
            'find' => 'Lane', 'book' => $this->book->id, 'act' => $chapterA->act_id,
        ]));

        $this->assertSame([$inAct->id], array_map(fn ($group) => $group->scene->id, $preview->scenes));
    }

    public function test_an_act_with_no_chapters_gives_no_matches_not_the_whole_book(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'Lane');
        $emptyAct = Act::factory()->for($this->book)->create();

        $preview = $this->previewData($this->preview([
            'find' => 'Lane', 'book' => $this->book->id, 'act' => $emptyAct->id,
        ]));

        $this->assertTrue($preview->isEmpty());
    }

    public function test_the_chapter_range_excludes_scenes_outside_it(): void
    {
        $chapters = [
            $this->chapterIn($this->book),
            $this->chapterIn($this->book),
            $this->chapterIn($this->book),
        ];
        $scenes = array_map(fn (Chapter $chapter) => $this->sceneIn($chapter, 'Lane'), $chapters);

        $preview = $this->previewData($this->preview([
            'find' => 'Lane',
            'book' => $this->book->id,
            'from_chapter' => $chapters[1]->id,
            'to_chapter' => $chapters[2]->id,
        ]));

        $this->assertSame(
            [$scenes[1]->id, $scenes[2]->id],
            array_map(fn ($group) => $group->scene->id, $preview->scenes),
        );
    }

    public function test_an_act_with_a_chapter_range_is_a_validation_error(): void
    {
        $chapter = $this->chapterIn($this->book);

        $this->preview([
            'find' => 'Lane', 'book' => $this->book->id, 'act' => $chapter->act_id, 'from_chapter' => $chapter->id,
        ])->assertSessionHasErrors('act');
    }

    public function test_a_book_of_another_project_fails_validation(): void
    {
        [, $otherBook] = $this->projectWithBook();

        $this->preview(['find' => 'Lane', 'book' => $otherBook->id])->assertSessionHasErrors('book');
    }

    public function test_a_find_text_that_is_too_long_fails_validation(): void
    {
        $this->preview(['find' => str_repeat('a', FindReplaceQuery::MAX_FIND_LENGTH + 1)])
            ->assertSessionHasErrors('find');
    }

    public function test_a_replace_text_that_is_too_long_fails_validation(): void
    {
        $this->preview(['find' => 'a', 'replace' => str_repeat('a', FindReplaceQuery::MAX_REPLACE_LENGTH + 1)])
            ->assertSessionHasErrors('replace');
    }

    public function test_scenes_start_open_with_twenty_matches_or_fewer_and_folded_above(): void
    {
        $chapter = $this->chapterIn($this->book);
        $this->sceneIn($chapter, str_repeat('Lane ', FindReplacePreview::OPEN_LIMIT));

        $this->assertMatchesRegularExpression('/<details\s+open/', $this->preview(['find' => 'Lane'])->getContent());

        $this->sceneIn($chapter, 'Lane');

        $this->assertDoesNotMatchRegularExpression('/<details\s+open/', $this->preview(['find' => 'Lane'])->getContent());
    }

    public function test_the_preview_stops_at_the_cap_and_says_so(): void
    {
        $chapter = $this->chapterIn($this->book);
        $this->sceneIn($chapter, str_repeat('Lane ', SceneFindReplace::MAX_MATCHES + 5));

        $response = $this->preview(['find' => 'Lane']);
        $preview = $this->previewData($response);

        $this->assertSame(SceneFindReplace::MAX_MATCHES, $preview->matchCount());
        $this->assertTrue($preview->capped);
        $response->assertSee(__('Showing the first :count matches. Narrow the scope to see the rest.', ['count' => SceneFindReplace::MAX_MATCHES]));
    }

    public function test_the_cap_counts_matches_across_scenes(): void
    {
        $chapter = $this->chapterIn($this->book);
        $each = SceneFindReplace::MAX_MATCHES / 2;
        $this->sceneIn($chapter, str_repeat('Lane ', $each));
        $this->sceneIn($chapter, str_repeat('Lane ', $each));

        $exact = $this->previewData($this->preview(['find' => 'Lane']));
        $this->assertSame(SceneFindReplace::MAX_MATCHES, $exact->matchCount());
        $this->assertFalse($exact->capped, 'Exactly the cap is not cut.');

        $this->sceneIn($chapter, 'one more Lane');

        $over = $this->previewData($this->preview(['find' => 'Lane']));
        $this->assertSame(SceneFindReplace::MAX_MATCHES, $over->matchCount());
        $this->assertTrue($over->capped);
        $this->assertCount(2, $over->scenes);
    }

    public function test_the_service_returns_an_empty_preview_for_a_blank_find(): void
    {
        $this->sceneIn($this->chapterIn($this->book), 'Lane');

        $preview = app(SceneFindReplace::class)->preview($this->project, new FindReplaceQuery(''), new SearchScope);

        $this->assertTrue($preview->isEmpty());
    }

    public function test_the_search_page_links_here_and_carries_the_query_over(): void
    {
        $this->actingAs($this->user)
            ->get(route('projects.search.index', ['project' => $this->project, 'q' => 'Lane']))
            ->assertOk()
            ->assertSee(e(route('projects.replace.index', ['project' => $this->project, 'find' => 'Lane'])), false);
    }

    public function test_the_tools_menu_and_the_tools_page_link_here(): void
    {
        $url = e(route('projects.replace.index', $this->project));

        $this->actingAs($this->user)
            ->get(route('projects.tools.home', $this->project))
            ->assertOk()
            ->assertSee($url, false);
    }

    public function test_the_page_runs_a_constant_number_of_queries(): void
    {
        $chapter = $this->chapterIn($this->book);
        $this->sceneIn($chapter, 'Lane');

        foreach (range(1, 10) as $number) {
            $this->sceneIn($this->chapterIn($this->book), 'Lane '.$number);
        }

        // The first request in a test run makes extra session and auth queries.
        $this->preview(['find' => 'Lane'])->assertOk();

        DB::enableQueryLog();
        $this->preview(['find' => 'Lane'])->assertOk();
        $many = count(DB::getQueryLog());

        Scene::query()->whereKeyNot($chapter->scenes()->first()->id)->delete();
        DB::flushQueryLog();
        $this->preview(['find' => 'Lane'])->assertOk();
        $few = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }
}
