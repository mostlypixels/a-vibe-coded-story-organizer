<?php

namespace Tests\Unit\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Support\ListJump;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ListJumpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 3 acts x 4 chapters x 5 scenes = 60 scenes, in story order. Every
     * chapter's first scene lands at a known offset (its index x 5), so an
     * off-by-one in the page arithmetic is visible.
     *
     * @return array{Book, list<int>}
     */
    private function makeBookWithChapters(): array
    {
        $book = Book::factory()->create();
        $chapterIds = [];

        for ($a = 0; $a < 3; $a++) {
            $act = Act::factory()->for($book)->create();

            for ($c = 0; $c < 4; $c++) {
                $chapter = Chapter::factory()->for($act)->create();
                Scene::factory()->for($chapter)->count(5)->create();
                $chapterIds[] = $chapter->id;
            }
        }

        return [$book, $chapterIds];
    }

    private function sceneQuery(Book $book): Builder
    {
        return $book->sceneQuery();
    }

    public function test_first_group_gives_page_one_with_no_query_needed(): void
    {
        [$book, $chapterIds] = $this->makeBookWithChapters();

        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, $chapterIds[0], perPage: 10);

        $this->assertSame(1, $page);
    }

    public function test_a_middle_group_gives_the_arithmetically_correct_page(): void
    {
        [$book, $chapterIds] = $this->makeBookWithChapters();

        // 7th chapter (index 6): 6 preceding chapters x 5 scenes = 30 rows before it.
        // intdiv(30, 10) + 1 = 4.
        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, $chapterIds[6], perPage: 10);

        $this->assertSame(4, $page);
    }

    public function test_the_last_group_gives_the_last_page_not_past_it(): void
    {
        [$book, $chapterIds] = $this->makeBookWithChapters();

        // Last chapter (index 11): 11 preceding chapters x 5 scenes = 55 rows before it.
        // intdiv(55, 10) + 1 = 6, the last of 60 rows / 10 per page.
        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, $chapterIds[11], perPage: 10);

        $this->assertSame(6, $page);
    }

    public function test_a_group_whose_first_row_is_the_last_row_of_a_page(): void
    {
        $book = Book::factory()->create();
        $act = Act::factory()->for($book)->create();
        $first = Chapter::factory()->for($act)->create();
        Scene::factory()->for($first)->count(9)->create();
        $second = Chapter::factory()->for($act)->create();
        Scene::factory()->for($second)->count(1)->create();

        // 9 rows before the second chapter: its first scene is row 10, the
        // last row of page 1 at page size 10.
        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', [$first->id, $second->id], $second->id, perPage: 10);

        $this->assertSame(1, $page);
    }

    public function test_a_group_whose_first_row_is_the_first_row_of_a_page(): void
    {
        $book = Book::factory()->create();
        $act = Act::factory()->for($book)->create();
        $first = Chapter::factory()->for($act)->create();
        Scene::factory()->for($first)->count(10)->create();
        $second = Chapter::factory()->for($act)->create();
        Scene::factory()->for($second)->count(1)->create();

        // 10 rows before the second chapter: its first scene is row 11, the
        // first row of page 2 at page size 10.
        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', [$first->id, $second->id], $second->id, perPage: 10);

        $this->assertSame(2, $page);
    }

    public function test_a_different_page_size_gives_a_different_page_for_the_same_target(): void
    {
        [$book, $chapterIds] = $this->makeBookWithChapters();

        $atTen = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, $chapterIds[6], perPage: 10);
        $atTwentyFive = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, $chapterIds[6], perPage: 25);

        $this->assertSame(4, $atTen);
        // 30 rows before it: intdiv(30, 25) + 1 = 2.
        $this->assertSame(2, $atTwentyFive);
        $this->assertNotSame($atTen, $atTwentyFive);
    }

    public function test_a_target_absent_from_the_ordered_ids_throws(): void
    {
        [$book, $chapterIds] = $this->makeBookWithChapters();

        $this->expectException(InvalidArgumentException::class);

        ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $chapterIds, 999999, perPage: 10);
    }

    /**
     * The ordering-drift guard: two sibling chapters share a `position`. The
     * page arithmetic must still follow the `id` tie-break, since that is
     * what the id-ordered $orderedIds list (and the index's own query) use
     * to break the tie. Drift here means the jump lands on the first
     * chapter's page instead of the second's.
     */
    public function test_sibling_chapters_sharing_a_position_still_land_on_the_second_chapters_own_page(): void
    {
        $book = Book::factory()->create();
        $act = Act::factory()->for($book)->create();

        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($first)->count(5)->create();

        $second = Chapter::factory()->for($act)->create(['position' => 1]);
        Scene::factory()->for($second)->count(5)->create();

        // Ordered by (position, id), as SceneController::index and
        // chaptersFor() both do: the tie on position=1 breaks on id, so the
        // second chapter (higher id) still comes after the first.
        $orderedIds = [$first->id, $second->id];

        $page = ListJump::page($this->sceneQuery($book), 'scenes.chapter_id', $orderedIds, $second->id, perPage: 10);

        // 5 rows before the second chapter's group.
        $this->assertSame(1, $page);
    }

    /**
     * Same guard, on the chapter list's act jump: two acts share a
     * `position`, and jumping to the second must count only the first
     * act's chapters as preceding it.
     */
    public function test_sibling_acts_sharing_a_position_still_land_on_the_second_acts_own_page(): void
    {
        $book = Book::factory()->create();

        $first = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($first)->count(3)->create();

        $second = Act::factory()->for($book)->create(['position' => 1]);
        Chapter::factory()->for($second)->count(3)->create();

        $orderedIds = [$first->id, $second->id];

        $page = ListJump::page($book->chapterQuery(), 'chapters.act_id', $orderedIds, $second->id, perPage: 2);

        // 3 chapters before the second act's group: intdiv(3, 2) + 1 = 2.
        $this->assertSame(2, $page);
    }
}
