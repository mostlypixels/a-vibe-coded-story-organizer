<?php

namespace Tests\Unit;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Support\StoryNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StoryNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chapters_and_scenes_number_continuously_across_acts(): void
    {
        [$project, $book] = $this->projectWithBook();

        $actOne = Act::factory()->for($book)->create();
        $actTwo = Act::factory()->for($book)->create();

        $chapterOne = Chapter::factory()->for($actOne)->create();
        $chapterTwo = Chapter::factory()->for($actOne)->create();
        $chapterThree = Chapter::factory()->for($actTwo)->create();
        $chapterFour = Chapter::factory()->for($actTwo)->create();

        $sceneOne = Scene::factory()->for($chapterOne)->create();
        $sceneTwo = Scene::factory()->for($chapterOne)->create();
        $sceneThree = Scene::factory()->for($chapterTwo)->create();
        $sceneFour = Scene::factory()->for($chapterThree)->create();
        $sceneFive = Scene::factory()->for($chapterFour)->create();

        $numbering = StoryNumbering::forBook($book);

        $this->assertSame(1, $numbering->chapter($chapterOne));
        $this->assertSame(2, $numbering->chapter($chapterTwo));
        $this->assertSame(3, $numbering->chapter($chapterThree));
        $this->assertSame(4, $numbering->chapter($chapterFour));

        // Scene numbers run unbroken across both the chapter and act boundary.
        $this->assertSame(1, $numbering->scene($sceneOne));
        $this->assertSame(2, $numbering->scene($sceneTwo));
        $this->assertSame(3, $numbering->scene($sceneThree));
        $this->assertSame(4, $numbering->scene($sceneFour));
        $this->assertSame(5, $numbering->scene($sceneFive));
    }

    public function test_deleting_a_middle_chapter_compacts_the_number_gap(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $chapters = [];
        foreach (range(1, 4) as $i) {
            $chapters[] = Chapter::factory()->for($act)->create();
        }
        [$first, $second, $third, $fourth] = $chapters;

        $second->delete();

        // `position` is untouched by the deletion — the survivors keep their
        // original, now-gappy positions.
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(3, $third->fresh()->position);
        $this->assertSame(4, $fourth->fresh()->position);

        $numbering = StoryNumbering::forBook($book);

        $this->assertSame(1, $numbering->chapter($first));
        $this->assertSame(2, $numbering->chapter($third));
        $this->assertSame(3, $numbering->chapter($fourth));
    }

    public function test_deleting_a_middle_act_compacts_the_number_gap(): void
    {
        [$project, $book] = $this->projectWithBook();

        $acts = [];
        foreach (range(1, 4) as $i) {
            $acts[] = Act::factory()->for($book)->create();
        }
        [$first, $second, $third, $fourth] = $acts;

        $second->delete();

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(3, $third->fresh()->position);
        $this->assertSame(4, $fourth->fresh()->position);

        $numbering = StoryNumbering::forBook($book);

        $this->assertSame(1, $numbering->act($first));
        $this->assertSame(2, $numbering->act($third));
        $this->assertSame(3, $numbering->act($fourth));
    }

    public function test_reordering_acts_renumbers_their_chapters_without_writing_chapter_position(): void
    {
        [$project, $book] = $this->projectWithBook();

        $actOne = Act::factory()->for($book)->create();
        $actTwo = Act::factory()->for($book)->create();

        $actOneChapterOne = Chapter::factory()->for($actOne)->create();
        $actOneChapterTwo = Chapter::factory()->for($actOne)->create();
        $actTwoChapterOne = Chapter::factory()->for($actTwo)->create();
        $actTwoChapterTwo = Chapter::factory()->for($actTwo)->create();

        $before = StoryNumbering::forBook($book);
        $this->assertSame(1, $before->chapter($actOneChapterOne));
        $this->assertSame(2, $before->chapter($actOneChapterTwo));
        $this->assertSame(3, $before->chapter($actTwoChapterOne));
        $this->assertSame(4, $before->chapter($actTwoChapterTwo));

        // Swap the acts' own positions — moveUp()/moveDown() never touch a
        // chapter row.
        $actTwo->moveUp();

        $after = StoryNumbering::forBook($book);

        $this->assertSame(1, $after->chapter($actTwoChapterOne));
        $this->assertSame(2, $after->chapter($actTwoChapterTwo));
        $this->assertSame(3, $after->chapter($actOneChapterOne));
        $this->assertSame(4, $after->chapter($actOneChapterTwo));

        $this->assertSame(1, $actOneChapterOne->fresh()->position);
        $this->assertSame(2, $actOneChapterTwo->fresh()->position);
        $this->assertSame(1, $actTwoChapterOne->fresh()->position);
        $this->assertSame(2, $actTwoChapterTwo->fresh()->position);
    }

    public function test_siblings_sharing_a_position_number_deterministically_by_id(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $chapterA = Chapter::factory()->for($act)->create(['position' => 1]);
        $chapterB = Chapter::factory()->for($act)->create(['position' => 1]);

        $this->assertTrue($chapterA->id < $chapterB->id);

        $numbering = StoryNumbering::forBook($book);

        $this->assertSame(1, $numbering->chapter($chapterA));
        $this->assertSame(2, $numbering->chapter($chapterB));
    }

    public function test_from_acts_and_for_book_produce_identical_maps(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $viaForBook = StoryNumbering::forBook($book);
        $viaFromActs = StoryNumbering::fromActs($project->acts()->with('chapters.scenes')->get());

        $this->assertSame($viaForBook->act($act), $viaFromActs->act($act));
        $this->assertSame($viaForBook->chapter($chapter), $viaFromActs->chapter($chapter));
        $this->assertSame($viaForBook->scene($scene), $viaFromActs->scene($scene));
    }

    public function test_from_acts_fires_no_queries(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $acts = $project->acts()->with('chapters.scenes')->get();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        StoryNumbering::fromActs($acts);

        $this->assertCount(0, $queries);
    }

    public function test_unknown_act_id_throws(): void
    {
        [, $book] = $this->projectWithBook();
        $numbering = StoryNumbering::forBook($book);

        $this->expectException(RuntimeException::class);

        $numbering->act(999999);
    }

    public function test_unknown_chapter_id_throws(): void
    {
        [, $book] = $this->projectWithBook();
        $numbering = StoryNumbering::forBook($book);

        $this->expectException(RuntimeException::class);

        $numbering->chapter(999999);
    }

    public function test_unknown_scene_id_throws(): void
    {
        [, $book] = $this->projectWithBook();
        $numbering = StoryNumbering::forBook($book);

        $this->expectException(RuntimeException::class);

        $numbering->scene(999999);
    }

    public function test_two_books_in_one_project_number_independently(): void
    {
        [$project, $bookOne] = $this->projectWithBook();
        $bookTwo = Book::factory()->for($project)->create();

        $bookOneAct = Act::factory()->for($bookOne)->create();
        $bookOneChapter = Chapter::factory()->for($bookOneAct)->create();
        Scene::factory()->for($bookOneChapter)->create();

        $bookTwoAct = Act::factory()->for($bookTwo)->create();
        $bookTwoChapter = Chapter::factory()->for($bookTwoAct)->create();
        $bookTwoScene = Scene::factory()->for($bookTwoChapter)->create();

        $numbering = StoryNumbering::forBook($bookTwo);

        // Book two's own tree starts back at 1, unaffected by book one's acts,
        // chapters and scenes sharing the same project.
        $this->assertSame(1, $numbering->act($bookTwoAct));
        $this->assertSame(1, $numbering->chapter($bookTwoChapter));
        $this->assertSame(1, $numbering->scene($bookTwoScene));
    }
}
