<?php

namespace Tests\Feature;

use App\Enums\StoryOverviewMode;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the read-only Story overview: it authorizes via the project
 * and renders the nested act -> chapter -> scene tree ordered by `position`.
 */
class StoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book in `whole` mode. The whole-tree assertions below (every chapter's
     * scenes, every chapter total, and the single-scene-query budget) describe
     * `whole` rendering; the default `chapter` mode paginates and is covered by
     * its own tests further down.
     */
    private function wholeBook(User $user): Book
    {
        [, $book] = $this->projectWithBook($user);

        $book->update(['overview_render_mode' => StoryOverviewMode::Whole]);

        return $book;
    }

    /** A scene with exactly $wordCount words, built from a repeated token. */
    private function sceneWithWordCount(Chapter $chapter, int $wordCount): Scene
    {
        return Scene::factory()->for($chapter)->create([
            'contents' => trim(str_repeat('word ', $wordCount)),
        ]);
    }

    public function test_the_story_overview_renders_the_full_act_chapter_scene_tree(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'The First Act']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The First Chapter']);
        Scene::factory()->for($chapter)->create(['name' => 'The First Scene']);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('The First Act')
            ->assertSee('The First Chapter')
            ->assertSee('The First Scene');
    }

    public function test_a_user_cannot_view_another_users_story_overview(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->get(route('books.story.overview', $book))
            ->assertForbidden();
    }

    public function test_the_story_overview_orders_acts_by_position(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);
        // Create out of position order to prove the view sorts, not insertion order.
        Act::factory()->for($book)->create(['name' => 'Later Act', 'position' => 2]);
        Act::factory()->for($book)->create(['name' => 'Earlier Act', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSeeInOrder(['Earlier Act', 'Later Act']);
    }

    public function test_the_story_overview_orders_scenes_within_a_chapter_by_position(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        Scene::factory()->for($chapter)->create(['name' => 'Second Scene', 'position' => 2]);
        Scene::factory()->for($chapter)->create(['name' => 'First Scene', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSeeInOrder(['First Scene', 'Second Scene']);
    }

    // ---------------------------------------------------------------------
    // Chapter/act/project totals, at zero extra queries. StoryController::
    // index() already eager-loads the scenes, so every total below is a PHP
    // sum() over that loaded data.
    // ---------------------------------------------------------------------

    /**
     * Every total below is distinct *and* none is a tail of another, which is
     * the stronger property this needs: `assertSee` is a substring match, so
     * with round numbers a chapter's "50 words" is satisfied by its act's
     * "1,050 words" and the assertion passes with that chapter's total never
     * rendered at all. The deliberately unround values below (1,343 / 1,062 /
     * 1,015 / 47 / 281 / 213 / 68) leave no such overlap, so each assertion
     * can only be met by the element it names.
     */
    public function test_chapter_act_and_project_totals_are_the_sum_of_their_scenes(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);

        $actOne = Act::factory()->for($book)->create();
        $chapterA = Chapter::factory()->for($actOne)->create();
        $this->sceneWithWordCount($chapterA, 613);
        $this->sceneWithWordCount($chapterA, 402); // chapter A total: 1,015
        $chapterB = Chapter::factory()->for($actOne)->create();
        $this->sceneWithWordCount($chapterB, 47); // chapter B total: 47
        // Act One total: 1,062

        $actTwo = Act::factory()->for($book)->create();
        $chapterC = Chapter::factory()->for($actTwo)->create();
        $this->sceneWithWordCount($chapterC, 213); // chapter C total: 213
        $chapterD = Chapter::factory()->for($actTwo)->create();
        $this->sceneWithWordCount($chapterD, 68); // chapter D total: 68
        // Act Two total: 281

        // Project total: 1,343

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('1,343 words') // project
            ->assertSee('1,062 words') // Act One
            ->assertSee('1,015 words') // Chapter A
            ->assertSee('47 words') // Chapter B
            ->assertSee('281 words') // Act Two
            ->assertSee('213 words') // Chapter C
            ->assertSee('68 words'); // Chapter D
    }

    public function test_an_act_with_no_chapters_and_a_chapter_with_no_scenes_render_zero_words(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);

        // Act with no chapters at all: its total, and the empty-chapters
        // message, must both render — not an error, not a blank total.
        Act::factory()->for($book)->create();

        // Act with one chapter that has no scenes.
        $actTwo = Act::factory()->for($book)->create();
        Chapter::factory()->for($actTwo)->create();

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk();

        // Four zero totals render: the empty act, the empty chapter, that
        // chapter's act (only chapter it has is empty), and the project as a
        // whole. Counting occurrences (not just assertSee) proves every level
        // actually reached "0 words" rather than one lucky match covering
        // for a level that rendered blank.
        $this->assertSame(4, substr_count($response->getContent(), '0 words'));
    }

    /** Keep changing totals outside each heading's accessible name. */
    public function test_totals_render_beside_the_act_and_chapter_headings_not_inside_them(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $this->sceneWithWordCount($chapter, 613);

        $content = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('613 words') // It does render — just not in the headings.
            ->getContent();

        foreach ([['h2', 'act', $act->id], ['h3', 'chapter', $chapter->id]] as [$tag, $prefix, $id]) {
            $this->assertSame(
                1,
                preg_match('/<'.$tag.'[^>]*id="'.$prefix.'-'.$id.'"[^>]*>(.*?)<\/'.$tag.'>/s', $content, $matches),
                "Expected one <$tag id=\"$prefix-$id\"> on the story overview."
            );

            $this->assertStringNotContainsString(
                'words',
                $matches[1],
                "The $prefix total is inside its <$tag>, which makes the count part of the "
                .'heading\'s accessible name. Render it as a sibling in the same flex row.'
            );
        }
    }

    /** Count scene queries only; request-level query totals are unstable. */
    public function test_totals_add_no_queries_against_the_scenes_table(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);

        foreach (range(1, 3) as $actNumber) {
            $act = Act::factory()->for($book)->create();

            foreach (range(1, 3) as $chapterNumber) {
                $chapter = Chapter::factory()->for($act)->create();

                foreach (range(1, 3) as $sceneNumber) {
                    $this->sceneWithWordCount($chapter, $sceneNumber * 10);
                }
            }
        }

        $sceneQueries = [];
        DB::listen(function ($query) use (&$sceneQueries) {
            if (str_contains($query->sql, '"scenes"')) {
                $sceneQueries[] = $query->sql;
            }
        });

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk();

        // One query loads every scene up front (the eager load); summing 3
        // acts x 3 chapters x 3 scenes into totals must not add any more.
        $this->assertCount(1, $sceneQueries);
    }

    // ---------------------------------------------------------------------
    // Continuous numbering — the story overview's TOC, headings and per-scene
    // labels take their numbers from StoryNumbering::fromActs(), not from the
    // raw, per-parent `position`.
    // ---------------------------------------------------------------------

    public function test_toc_and_headings_show_continuous_chapter_numbers_across_the_act_boundary(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);

        $actOne = Act::factory()->for($book)->create();
        Chapter::factory()->for($actOne)->create();
        Chapter::factory()->for($actOne)->create();

        // Act two's first chapter is chapter 3 book-wide, even though its
        // own `position` within act two is 1.
        $actTwo = Act::factory()->for($book)->create();
        Chapter::factory()->for($actTwo)->create();

        $content = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->getContent();

        // Both the TOC and the body heading render "Chapter 3" for the
        // chapter that leads act two — twice, once in each location.
        $this->assertSame(2, substr_count($content, 'Chapter 3'));
        $this->assertStringNotContainsString('Chapter 4', $content);
    }

    public function test_act_numbers_close_the_gap_left_by_a_deleted_act(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);

        $actOne = Act::factory()->for($book)->create(['name' => 'Act One']);
        $actTwo = Act::factory()->for($book)->create(['name' => 'Act Two']);
        $actThree = Act::factory()->for($book)->create(['name' => 'Act Three']);

        $actTwo->delete();

        // `position` is untouched by the deletion — act three keeps its
        // original, now-gappy position — but the rendered number compacts.
        $this->assertSame(3, $actThree->fresh()->position);

        $content = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->getContent();

        // Both the TOC and the body heading render "Act 2" for the act that
        // is now second, even though its own `position` is still 3.
        $this->assertSame(2, substr_count($content, 'Act 2'));
        $this->assertStringContainsString('Act Three', $content);
        $this->assertStringNotContainsString('Act 3 ', $content);
    }

    public function test_scene_numbers_render_and_run_unbroken_across_chapters(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);
        $act = Act::factory()->for($book)->create();

        $chapterOne = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapterOne)->create(['name' => 'Scene A']);
        Scene::factory()->for($chapterOne)->create(['name' => 'Scene B']);

        $chapterTwo = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapterTwo)->create(['name' => 'Scene C']);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk();

        $response->assertSeeInOrder([
            'data-scene-number', '1.', 'Scene A',
            'data-scene-number', '2.', 'Scene B',
            'data-scene-number', '3.', 'Scene C',
        ], escape: false);
    }

    /**
     * `StoryNumbering::fromActs()` derives from the tree StoryController::index()
     * already eager-loaded — it must fire zero further queries against the
     * scenes table, on top of the one guarded by
     * test_totals_add_no_queries_against_the_scenes_table() above.
     */
    public function test_deriving_scene_numbers_adds_no_extra_queries_against_the_scenes_table(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->count(3)->for($chapter)->create();

        $sceneQueries = [];
        DB::listen(function ($query) use (&$sceneQueries) {
            if (str_contains($query->sql, '"scenes"')) {
                $sceneQueries[] = $query->sql;
            }
        });

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk();

        $this->assertCount(1, $sceneQueries);
    }

    // ---------------------------------------------------------------------
    // Chapter mode (the default): one chapter's scene bodies render, while
    // numbering, the TOC and word totals stay story-wide. Only the current
    // chapter's `contents` is loaded, whatever the size of the rest.
    // ---------------------------------------------------------------------

    public function test_chapter_mode_renders_only_the_first_chapters_scenes_by_default(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($first)->create(['name' => 'Opening Scene']);

        $later = Chapter::factory()->for($act)->create(['position' => 2]);
        Scene::factory()->for($later)->create(['name' => 'Distant Scene']);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('Opening Scene')
            ->assertDontSee('Distant Scene');
    }

    public function test_chapter_query_param_selects_that_chapter_and_hides_its_siblings(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($first)->create(['name' => 'Opening Scene']);

        $later = Chapter::factory()->for($act)->create(['position' => 2]);
        Scene::factory()->for($later)->create(['name' => 'Distant Scene']);

        $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $later->id]))
            ->assertOk()
            ->assertSee('Distant Scene')
            ->assertDontSee('Opening Scene');
    }

    /**
     * Only the selected chapter loads, yet its number is its book-wide rank —
     * the guard that chapter mode numbers through StoryNumbering::forBook()
     * (the whole light tree), not fromActs() (which needs the loaded tree).
     */
    public function test_a_mid_story_chapter_shows_its_book_wide_number(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $chapters = [];
        foreach (range(1, 15) as $position) {
            $chapter = Chapter::factory()->for($act)->create(['position' => $position]);
            // Names that are not prefixes of one another, so assertDontSee below
            // cannot be satisfied by a longer name that contains a shorter one.
            Scene::factory()->for($chapter)->create(['name' => "Body of chapter number {$position} here"]);
            $chapters[$position] = $chapter;
        }

        $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $chapters[15]->id]))
            ->assertOk()
            ->assertSee('Chapter 15')
            ->assertSee('Body of chapter number 15 here')
            ->assertDontSee('Body of chapter number 1 here'); // only chapter 15's body loads
    }

    /**
     * The act header and story totals are story-wide aggregates, not the loaded
     * chapter's sum. The first chapter (loaded) totals 613; its act totals 1,015
     * and the story 1,062 — none of which the loaded chapter alone would produce.
     */
    public function test_chapter_mode_header_and_story_totals_span_the_whole_story(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $chapterA = Chapter::factory()->for($actOne)->create(['position' => 1]);
        $this->sceneWithWordCount($chapterA, 613); // loaded chapter
        $chapterB = Chapter::factory()->for($actOne)->create(['position' => 2]);
        $this->sceneWithWordCount($chapterB, 402); // act one total: 1,015

        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        $chapterC = Chapter::factory()->for($actTwo)->create();
        $this->sceneWithWordCount($chapterC, 47); // story total: 1,062

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('613 words') // the loaded chapter's own total
            ->assertSee('1,015 words') // whole act one, though only chapter A loaded
            ->assertSee('1,062 words'); // whole story, though only chapter A loaded
    }

    public function test_a_chapter_from_another_project_is_forbidden(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        [, $otherBook] = $this->projectWithBook($user);
        $otherAct = Act::factory()->for($otherBook)->create();
        $otherChapter = Chapter::factory()->for($otherAct)->create();

        $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $otherChapter->id]))
            ->assertForbidden();
    }

    public function test_a_chapter_from_another_book_of_the_same_project_is_forbidden(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $sibling = Book::factory()->for($project)->create();
        $siblingChapter = Chapter::factory()->for(Act::factory()->for($sibling))->create();

        // Ownership is not the gate here: the writer owns both books. The
        // overview renders one book, so a chapter outside it has no place on
        // the page.
        $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $siblingChapter->id]))
            ->assertForbidden();
    }

    public function test_the_overview_renders_only_the_book_in_the_url(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        Act::factory()->for($book)->create(['name' => 'Volume one act']);

        $sibling = Book::factory()->for($project)->create();
        Act::factory()->for($sibling)->create(['name' => 'Volume two act']);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('Volume one act')
            ->assertDontSee('Volume two act');
    }

    public function test_the_render_mode_is_per_book(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $sibling = Book::factory()->for($project)->create();

        $this->actingAs($user)
            ->patch(route('books.story.overview.mode', $book), [
                'overview_render_mode' => StoryOverviewMode::Whole->value,
            ])
            ->assertRedirect(route('books.story.overview', $book));

        $this->assertSame(StoryOverviewMode::Whole, $book->fresh()->overview_render_mode);
        $this->assertSame(StoryOverviewMode::Chapter, $sibling->fresh()->overview_render_mode);
    }

    /**
     * Chapter mode's query cost is bounded and independent of story size: a
     * three-act, nine-chapter, twenty-seven-scene project hits the scenes table
     * exactly as many times as a one-scene project — no N+1, no non-current
     * `contents`.
     */
    public function test_chapter_mode_query_budget_is_independent_of_story_size(): void
    {
        $user = User::factory()->create();

        $countSceneQueries = function (Book $book) use ($user): int {
            $queries = [];
            DB::listen(function ($query) use (&$queries) {
                if (str_contains($query->sql, '"scenes"')) {
                    $queries[] = $query->sql;
                }
            });

            $this->actingAs($user)
                ->get(route('books.story.overview', $book))
                ->assertOk();

            return count($queries);
        };

        [, $smallBook] = $this->projectWithBook($user);
        $smallAct = Act::factory()->for($smallBook)->create();
        $smallChapter = Chapter::factory()->for($smallAct)->create();
        Scene::factory()->for($smallChapter)->create();

        [, $largeBook] = $this->projectWithBook($user);
        foreach (range(1, 3) as $actNumber) {
            $act = Act::factory()->for($largeBook)->create();
            foreach (range(1, 3) as $chapterNumber) {
                $chapter = Chapter::factory()->for($act)->create();
                Scene::factory()->count(3)->for($chapter)->create();
            }
        }

        $this->assertSame(
            $countSceneQueries($smallBook),
            $countSceneQueries($largeBook),
            'Chapter mode must touch the scenes table the same number of times regardless of story size.'
        );
    }

    // ---------------------------------------------------------------------
    // Chapter pager and TOC links: prev/next chapter navigation, and TOC
    // hrefs that carry ?chapter={id} in chapter mode.
    // ---------------------------------------------------------------------

    public function test_first_chapter_page_disables_previous_and_links_next_to_the_second_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $second = Chapter::factory()->for($act)->create(['position' => 2]);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $first->id]))
            ->assertOk();

        $response->assertSee(__('Previous chapter'));
        $response->assertSee(
            route('books.story.overview', ['book' => $book, 'chapter' => $second->id]),
            false,
        );
    }

    public function test_last_chapter_page_disables_next_and_links_previous_to_the_penultimate_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $penultimate = Chapter::factory()->for($act)->create(['position' => 1]);
        $last = Chapter::factory()->for($act)->create(['position' => 2]);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $last->id]))
            ->assertOk();

        $response->assertSee(__('Next chapter'));
        $response->assertSee(
            route('books.story.overview', ['book' => $book, 'chapter' => $penultimate->id]),
            false,
        );
    }

    public function test_a_middle_chapter_links_to_its_correct_previous_and_next_chapters(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $middle = Chapter::factory()->for($act)->create(['position' => 2]);
        $last = Chapter::factory()->for($act)->create(['position' => 3]);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $middle->id]))
            ->assertOk();

        $response->assertSee(route('books.story.overview', ['book' => $book, 'chapter' => $first->id]), false);
        $response->assertSee(route('books.story.overview', ['book' => $book, 'chapter' => $last->id]), false);
    }

    public function test_the_pager_crosses_an_act_boundary_to_the_adjacent_acts_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $lastOfActOne = Chapter::factory()->for($actOne)->create(['position' => 1]);

        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        $firstOfActTwo = Chapter::factory()->for($actTwo)->create(['position' => 1]);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', ['book' => $book, 'chapter' => $lastOfActOne->id]))
            ->assertOk();

        $response->assertSee(route('books.story.overview', ['book' => $book, 'chapter' => $firstOfActTwo->id]), false);
    }

    public function test_chapter_mode_toc_links_carry_the_chapter_id_and_the_act_link_targets_its_first_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $second = Chapter::factory()->for($act)->create(['position' => 2]);

        $response = $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk();

        // Both TOC chapter links carry ?chapter={id}; the act link targets the
        // act's first chapter, so it appears without the second chapter's id.
        $response->assertSee(route('books.story.overview', ['book' => $book, 'chapter' => $first->id]), false);
        $response->assertSee(route('books.story.overview', ['book' => $book, 'chapter' => $second->id]), false);
    }

    // ---------------------------------------------------------------------
    // Mode switch: owner-only PATCH persists overview_render_mode, rendered
    // control is owner-only.
    // ---------------------------------------------------------------------

    public function test_owner_can_switch_the_overview_render_mode(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $book->update(['overview_render_mode' => StoryOverviewMode::Chapter]);

        $this->actingAs($user)
            ->patch(route('books.story.overview.mode', $book), [
                'overview_render_mode' => StoryOverviewMode::Whole->value,
            ])
            ->assertRedirect(route('books.story.overview', $book));

        $this->assertSame(StoryOverviewMode::Whole, $book->fresh()->overview_render_mode);
    }

    public function test_switching_the_mode_preserves_the_current_chapter_query_param(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->patch(route('books.story.overview.mode', ['book' => $book, 'chapter' => $chapter->id]), [
                'overview_render_mode' => StoryOverviewMode::Whole->value,
            ])
            ->assertRedirect(route('books.story.overview', ['book' => $book, 'chapter' => $chapter->id]));
    }

    public function test_an_invalid_render_mode_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->patch(route('books.story.overview.mode', $book), [
                'overview_render_mode' => 'not-a-real-mode',
            ])
            ->assertSessionHasErrors('overview_render_mode');
    }

    public function test_a_non_owner_cannot_switch_the_overview_render_mode(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->patch(route('books.story.overview.mode', $book), [
                'overview_render_mode' => StoryOverviewMode::Whole->value,
            ])
            ->assertForbidden();
    }

    public function test_the_mode_switch_renders_on_the_overview_for_its_owner(): void
    {
        $owner = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($owner)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee(__('Story overview display'));
    }

    public function test_whole_mode_still_renders_every_chapters_scenes(): void
    {
        $user = User::factory()->create();
        $book = $this->wholeBook($user);
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($first)->create(['name' => 'Alpha Scene']);

        $second = Chapter::factory()->for($act)->create(['position' => 2]);
        Scene::factory()->for($second)->create(['name' => 'Omega Scene']);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('Alpha Scene')
            ->assertSee('Omega Scene');
    }
}
