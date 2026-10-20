<?php

namespace Tests\Unit\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Support\EditorOutline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorOutlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_siblings_with_equal_position_sort_by_id(): void
    {
        [, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create(['position' => 1]);
        $laterChapter = Chapter::factory()->for($act)->create(['position' => 1]);
        $earlierChapter = Chapter::factory()->for($act)->create(['position' => 1]);
        $first = Scene::factory()->for($laterChapter)->create(['position' => 1]);
        $second = Scene::factory()->for($laterChapter)->create(['position' => 1]);

        $outline = EditorOutline::forBook($book);

        $this->assertSame([$laterChapter->id, $earlierChapter->id], $outline->chapters()->pluck('id')->all());
        $this->assertSame([$first->id, $second->id], $outline->acts()->first()->chapters->first()->scenes->pluck('id')->all());
    }

    public function test_has_single_act(): void
    {
        [, $book] = $this->projectWithBook();
        Act::factory()->for($book)->create();

        $this->assertTrue(EditorOutline::forBook($book)->hasSingleAct());

        Act::factory()->for($book)->create();

        $this->assertFalse(EditorOutline::forBook($book)->hasSingleAct());
    }

    public function test_chapters_cross_acts_in_story_order_with_the_act_set(): void
    {
        [, $book] = $this->projectWithBook();
        $secondAct = Act::factory()->for($book)->create(['position' => 2]);
        $firstAct = Act::factory()->for($book)->create(['position' => 1]);
        $inSecond = Chapter::factory()->for($secondAct)->create(['position' => 1]);
        $firstB = Chapter::factory()->for($firstAct)->create(['position' => 2]);
        $firstA = Chapter::factory()->for($firstAct)->create(['position' => 1]);

        $outline = EditorOutline::forBook($book);
        $chapters = $outline->chapters();

        $this->assertSame([$firstA->id, $firstB->id, $inSecond->id], $chapters->pluck('id')->all());
        $this->assertSame([$firstAct->id, $firstAct->id, $secondAct->id], $chapters->pluck('act.id')->all());
        $this->assertSame(3, $outline->numbering()->chapter($inSecond));
    }

    public function test_it_ignores_other_books(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Chapter::factory()->for(Act::factory()->for(Book::factory()->for($project)->create()))->create();

        $this->assertSame([$chapter->id], EditorOutline::forBook($book)->chapters()->pluck('id')->all());
    }
}
