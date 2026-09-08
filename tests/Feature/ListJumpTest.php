<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Go-to button on the scene list: the page arithmetic aimed through a real
 * request, the redirect it answers with, and the marked rows it lands on.
 *
 * `ListPaginationTest` guards the bar and the paginator; this guards the aiming.
 * Both buttons share one select, so the "Filter still filters" tests below are
 * the regression that matters most.
 */
class ListJumpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 3 acts x 4 chapters x 5 scenes = 60 scenes at 10 a page. Every chapter's
     * first scene lands at a known offset, so an off-by-one is visible.
     *
     * @return array{User, Book, list<Chapter>}
     */
    private function bookWithTwelveChapters(): array
    {
        config(['pagination.default' => 10, 'pagination.sizes' => [10, 25]]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapters = [];

        for ($a = 1; $a <= 3; $a++) {
            $act = Act::factory()->for($book)->create(['name' => "Act {$a}", 'position' => $a]);

            for ($c = 1; $c <= 4; $c++) {
                $chapter = Chapter::factory()->for($act)->create(['name' => "Chapter {$a}-{$c}", 'position' => $c]);

                for ($s = 1; $s <= 5; $s++) {
                    Scene::factory()->for($chapter)->create(['name' => "Scene {$a}-{$c}-{$s}"]);
                }

                $chapters[] = $chapter;
            }
        }

        return [$user, $book, $chapters];
    }

    private function jumpUrl(Book $book, int $chapterId, array $extra = []): string
    {
        return route('books.scenes.index', ['book' => $book, 'chapter' => $chapterId, 'jump' => 1] + $extra);
    }

    // --- Jump resolution --------------------------------------------------

    public function test_a_jump_lands_on_the_page_holding_the_chapters_first_scene(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $target = $chapters[6];

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $target->id));

        // 6 chapters x 5 scenes = 30 rows before it: intdiv(30, 10) + 1 = 4.
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('page=4', $location);
        $this->assertStringEndsWith('#chapter-'.$target->id, $location);
        $this->assertStringContainsString('highlight='.$target->id, $location);
        $this->assertStringContainsString('sort=position', $location);
        $this->assertStringContainsString('direction=asc', $location);
        $this->assertStringNotContainsString('jump', $location);
    }

    public function test_the_first_chapter_lands_on_page_one_without_a_redirect_loop(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $chapters[0]->id));

        $response->assertRedirect();
        $this->assertStringContainsString('page=1', $response->headers->get('Location'));

        $landed = $this->actingAs($user)->get($response->headers->get('Location'));
        $landed->assertOk();
    }

    public function test_the_last_chapter_lands_on_the_page_holding_its_first_scene(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $target = $chapters[11];

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $target->id));

        // 11 chapters x 5 scenes = 55 rows before it: intdiv(55, 10) + 1 = 6.
        $response->assertRedirect();
        $this->assertStringContainsString('page=6', $response->headers->get('Location'));

        $landed = $this->actingAs($user)->get($response->headers->get('Location'));
        $landed->assertOk();
        $landed->assertSee('Scene 3-4-1');
    }

    /** A jump shows the whole book, so the landed page keeps its neighbours. */
    public function test_the_landed_page_is_not_filtered_to_the_target_chapter(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $chapters[6]->id));
        $landed = $this->actingAs($user)->get($response->headers->get('Location'));

        $landed->assertOk();
        $landed->assertSee('Scene 2-3-1');
        // Page 4 holds rows 31-40: the target chapter's five, then its neighbour's.
        $landed->assertSee('Scene 2-4-1');
    }

    /** The whole point of the "always" rule: not only when the target has no matches. */
    public function test_the_search_is_cleared_even_when_the_target_matches_it(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get(
            $this->jumpUrl($book, $chapters[6]->id, ['search' => 'Scene 2-3'])
        );

        $response->assertRedirect();
        $this->assertStringNotContainsString('search', $response->headers->get('Location'));
    }

    public function test_the_filter_is_cleared_and_the_sort_is_forced_back_to_story_order(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get(
            $this->jumpUrl($book, $chapters[6]->id, ['sort' => 'name', 'direction' => 'desc'])
        );

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringNotContainsString('chapter=', $location);
        $this->assertStringContainsString('sort=position', $location);
        $this->assertStringContainsString('direction=asc', $location);
        $this->assertStringNotContainsString('sort=name', $location);
    }

    public function test_a_larger_page_size_puts_the_same_chapter_on_a_different_page(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $user->update(['page_size' => 25]);

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $chapters[6]->id));

        // 30 rows before it: intdiv(30, 25) + 1 = 2, where page size 10 gives 4.
        $response->assertRedirect();
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));
    }

    /** $groups is book-scoped, so a foreign id is absent, not a foreign lookup. */
    public function test_a_chapter_from_another_book_falls_through_to_a_plain_page_one(): void
    {
        [$user, $book] = $this->bookWithTwelveChapters();
        $otherChapter = Chapter::factory()->for(Act::factory())->create();

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $otherChapter->id));

        $response->assertRedirect(route('books.scenes.index', $book));
        $location = $response->headers->get('Location');
        $this->assertStringNotContainsString('highlight', $location);
        $this->assertStringNotContainsString('jump', $location);
    }

    public function test_a_non_owner_is_forbidden_on_the_jump_url(): void
    {
        [, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs(User::factory()->create())->get($this->jumpUrl($book, $chapters[6]->id));

        $response->assertForbidden();
    }

    /**
     * The ordering-drift guard. Two sibling chapters share a `position`, so only
     * the `id` tie-break tells them apart. If chaptersFor() and the index's own
     * ordering ever drift, the jump lands a page off and nothing else fails.
     */
    public function test_sibling_chapters_sharing_a_position_still_land_on_the_second_chapters_page(): void
    {
        config(['pagination.default' => 10]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['position' => 1]);

        $first = Chapter::factory()->for($act)->create(['name' => 'First', 'position' => 1]);
        Scene::factory()->for($first)->count(12)->create();

        $second = Chapter::factory()->for($act)->create(['name' => 'Second', 'position' => 1]);
        Scene::factory()->for($second)->create(['name' => 'The tied chapters scene']);

        $response = $this->actingAs($user)->get($this->jumpUrl($book, $second->id));

        // 12 rows before it: intdiv(12, 10) + 1 = 2, not the first chapter's page 1.
        $response->assertRedirect();
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));

        $landed = $this->actingAs($user)->get($response->headers->get('Location'));
        $landed->assertSee('The tied chapters scene');
    }

    // --- Filter is untouched ----------------------------------------------

    public function test_the_filter_still_filters_when_no_jump_is_sent(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $target = $chapters[6];

        $response = $this->actingAs($user)->get(
            route('books.scenes.index', ['book' => $book, 'chapter' => $target->id])
        );

        $response->assertOk();
        $response->assertSee('Scene 2-3-1');
        $response->assertDontSee('Scene 2-4-1');
        $this->assertSame(5, $response->viewData('scenes')->total());
        // The move buttons render only under a chapter filter.
        $response->assertSee(route('scenes.move-down', $target->scenes()->first()), false);
    }

    // --- Highlight and anchor ---------------------------------------------

    public function test_the_highlight_marks_only_that_chapters_rows_and_anchors_the_first(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $target = $chapters[6];

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'page' => 4,
            'highlight' => $target->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        // Page 4 holds this chapter's five scenes and five of its neighbour's.
        $this->assertSame(5, substr_count($html, 'bg-highlight/25'));
        // An id must be unique: only the group's first row on the page carries it.
        $this->assertSame(1, substr_count($html, 'id="chapter-'.$target->id.'"'));
    }

    /**
     * Colour is not the only signal: a highlighted row also carries the left
     * marker. The missing-event marker wins on a scene with no event, because
     * that one reports a problem in the data.
     */
    public function test_a_highlighted_row_carries_the_accent_marker_unless_its_event_is_missing(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();
        $target = $chapters[6];
        $event = Event::factory()->for($book->project)->create();
        $withEvent = $target->scenes()->orderBy('id')->pluck('id')->take(3);
        Scene::whereIn('id', $withEvent)->update(['event_id' => $event->id]);

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'page' => 4,
            'highlight' => $target->id,
        ]));

        $response->assertOk();

        // The page chrome carries left markers of its own, so the same page
        // without a highlight is the baseline this counts against.
        $plain = $this->actingAs($user)->get(route('books.scenes.index', ['book' => $book, 'page' => 4]));
        $plain->assertOk();

        $this->assertSame(
            substr_count($plain->getContent(), 'border-l-4 border-accent') + 3,
            substr_count($response->getContent(), 'border-l-4 border-accent')
        );
        // The chapter's two scenes with no event keep the missing-event marker.
        $this->assertSame(
            substr_count($plain->getContent(), 'border-l-4 border-danger'),
            substr_count($response->getContent(), 'border-l-4 border-danger')
        );
    }

    public function test_the_highlight_survives_a_page_link(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'page' => 4,
            'highlight' => $chapters[6]->id,
        ]));

        $response->assertOk();
        $response->assertSee('highlight='.$chapters[6]->id, false);
    }

    public function test_an_unknown_highlight_marks_nothing_and_does_not_error(): void
    {
        [$user, $book] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'highlight' => 999999,
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringNotContainsString('bg-highlight', $html);
        $this->assertStringNotContainsString('id="chapter-999999"', $html);
    }

    // --- Acts list: same code path, aimed at the chapter list --------------

    /**
     * 4 acts x 3 chapters = 12 chapters at 5 a page.
     *
     * @return array{User, Book, list<Act>}
     */
    private function bookWithFourActs(): array
    {
        config(['pagination.default' => 5, 'pagination.sizes' => [5, 25]]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $acts = [];

        for ($a = 1; $a <= 4; $a++) {
            $act = Act::factory()->for($book)->create(['name' => "Act {$a}", 'position' => $a]);

            for ($c = 1; $c <= 3; $c++) {
                Chapter::factory()->for($act)->create(['name' => "Chapter {$a}-{$c}", 'position' => $c]);
            }

            $acts[] = $act;
        }

        return [$user, $book, $acts];
    }

    private function actJumpUrl(Book $book, int $actId, array $extra = []): string
    {
        return route('books.chapters.index', ['book' => $book, 'act' => $actId, 'jump' => 1] + $extra);
    }

    public function test_an_act_jump_lands_on_the_page_holding_its_first_chapter(): void
    {
        [$user, $book, $acts] = $this->bookWithFourActs();
        $target = $acts[2];

        $response = $this->actingAs($user)->get($this->actJumpUrl($book, $target->id));

        // 2 acts x 3 chapters = 6 rows before it: intdiv(6, 5) + 1 = 2.
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('page=2', $location);
        $this->assertStringEndsWith('#act-'.$target->id, $location);
        $this->assertStringContainsString('highlight='.$target->id, $location);
        $this->assertStringContainsString('sort=position', $location);
        $this->assertStringContainsString('direction=asc', $location);
        $this->assertStringNotContainsString('jump', $location);
    }

    public function test_the_search_is_cleared_even_when_the_target_act_matches_it(): void
    {
        [$user, $book, $acts] = $this->bookWithFourActs();

        $response = $this->actingAs($user)->get(
            $this->actJumpUrl($book, $acts[2]->id, ['search' => 'Chapter 3'])
        );

        $response->assertRedirect();
        $this->assertStringNotContainsString('search', $response->headers->get('Location'));
    }

    /** $groups is book-scoped, so a foreign act id is absent, not a foreign lookup. */
    public function test_an_act_from_another_book_falls_through_to_a_plain_page_one(): void
    {
        [$user, $book] = $this->bookWithFourActs();
        $otherAct = Act::factory()->create();

        $response = $this->actingAs($user)->get($this->actJumpUrl($book, $otherAct->id));

        $response->assertRedirect(route('books.chapters.index', $book));
        $location = $response->headers->get('Location');
        $this->assertStringNotContainsString('highlight', $location);
        $this->assertStringNotContainsString('jump', $location);
    }

    public function test_a_non_owner_is_forbidden_on_the_act_jump_url(): void
    {
        [, $book, $acts] = $this->bookWithFourActs();

        $response = $this->actingAs(User::factory()->create())->get($this->actJumpUrl($book, $acts[2]->id));

        $response->assertForbidden();
    }

    /**
     * The ordering-drift guard for acts. Two acts share a `position`, so only the
     * `id` tie-break tells them apart. If actsFor() and the index's own ordering
     * ever drift, the jump lands a page off and nothing else fails.
     */
    public function test_sibling_acts_sharing_a_position_still_land_on_the_second_acts_page(): void
    {
        config(['pagination.default' => 5]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $first = Act::factory()->for($book)->create(['name' => 'First', 'position' => 1]);
        Chapter::factory()->for($first)->count(6)->create();

        $second = Act::factory()->for($book)->create(['name' => 'Second', 'position' => 1]);
        Chapter::factory()->for($second)->create(['name' => 'The tied acts chapter']);

        $response = $this->actingAs($user)->get($this->actJumpUrl($book, $second->id));

        // 6 rows before it: intdiv(6, 5) + 1 = 2, not the first act's page 1.
        $response->assertRedirect();
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));

        $landed = $this->actingAs($user)->get($response->headers->get('Location'));
        $landed->assertSee('The tied acts chapter');
    }

    public function test_the_act_filter_still_filters_when_no_jump_is_sent(): void
    {
        [$user, $book, $acts] = $this->bookWithFourActs();
        $target = $acts[2];

        $response = $this->actingAs($user)->get(
            route('books.chapters.index', ['book' => $book, 'act' => $target->id])
        );

        $response->assertOk();
        $response->assertSee('Chapter 3-1');
        $response->assertDontSee('Chapter 4-1');
        $this->assertSame(3, $response->viewData('chapters')->total());
    }

    public function test_the_act_highlight_marks_only_that_acts_rows_and_anchors_the_first(): void
    {
        [$user, $book, $acts] = $this->bookWithFourActs();
        $target = $acts[2];

        $response = $this->actingAs($user)->get(route('books.chapters.index', [
            'book' => $book,
            'page' => 2,
            'highlight' => $target->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        // Page 2 holds this act's three chapters and two of its neighbour's.
        $this->assertSame(3, substr_count($html, 'bg-highlight/25'));
        // An id must be unique: only the group's first row on the page carries it.
        $this->assertSame(1, substr_count($html, 'id="act-'.$target->id.'"'));
    }

    // --- Page range ---------------------------------------------------------

    /** Page 1 holds only Chapter 1-1 through 1-4: one act, no "to". */
    public function test_the_scene_page_range_prints_one_chapter_name_when_the_page_holds_one(): void
    {
        config(['pagination.default' => 5]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Ash and Rust', 'position' => 1]);
        Scene::factory()->for($chapter)->count(5)->create();

        $response = $this->actingAs($user)->get(route('books.scenes.index', $book));

        $response->assertOk();
        $response->assertSee('Chapter 1 — Ash and Rust', false);
        $response->assertViewHas('pageRange', 'Chapter 1 — Ash and Rust');
    }

    public function test_the_scene_page_range_prints_the_first_and_last_chapter_when_the_page_spans_two(): void
    {
        [$user, $book, $chapters] = $this->bookWithTwelveChapters();

        // Page 1 holds the first ten scenes: chapters 1-1 and 1-2's five scenes each.
        $response = $this->actingAs($user)->get(route('books.scenes.index', $book));

        $response->assertOk();
        $response->assertSee('Chapter 1 — Chapter 1-1 to Chapter 2 — Chapter 1-2', false);
    }

    public function test_the_scene_page_range_is_absent_when_sorted_by_name(): void
    {
        [$user, $book] = $this->bookWithTwelveChapters();

        $response = $this->actingAs($user)->get(route('books.scenes.index', ['book' => $book, 'sort' => 'name']));

        $response->assertOk();
        $response->assertViewHas('pageRange', null);
    }

    public function test_the_scene_page_range_is_absent_on_an_empty_list(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $response = $this->actingAs($user)->get(route('books.scenes.index', $book));

        $response->assertOk();
        $response->assertViewHas('pageRange', null);
    }

    /** Page 1 holds only Act 1's three chapters: one act, no "to". */
    public function test_the_chapter_page_range_prints_one_act_name_when_the_page_holds_one(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'Ash and Rust', 'position' => 1]);
        Chapter::factory()->for($act)->count(3)->create();

        $response = $this->actingAs($user)->get(route('books.chapters.index', $book));

        $response->assertOk();
        $response->assertSee('Act 1 — Ash and Rust', false);
        $response->assertViewHas('pageRange', 'Act 1 — Ash and Rust');
    }

    public function test_the_chapter_page_range_prints_the_first_and_last_act_when_the_page_spans_two(): void
    {
        [$user, $book, $acts] = $this->bookWithFourActs();

        // Page 1 holds the first five chapters: all of Act 1 and two of Act 2.
        $response = $this->actingAs($user)->get(route('books.chapters.index', $book));

        $response->assertOk();
        $response->assertSee('Act 1 — Act 1 to Act 2 — Act 2', false);
    }

    public function test_the_chapter_page_range_is_absent_when_sorted_by_name(): void
    {
        [$user, $book] = $this->bookWithFourActs();

        $response = $this->actingAs($user)->get(route('books.chapters.index', ['book' => $book, 'sort' => 'name']));

        $response->assertOk();
        $response->assertViewHas('pageRange', null);
    }

    public function test_the_chapter_page_range_is_absent_on_an_empty_list(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $response = $this->actingAs($user)->get(route('books.chapters.index', $book));

        $response->assertOk();
        $response->assertViewHas('pageRange', null);
    }
}
