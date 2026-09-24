<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookScopeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chapter_and_scene_queries_return_this_books_rows_only(): void
    {
        $project = Project::factory()->create();
        $book = $project->books()->firstOrFail();
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create();

        // Rows of a sibling book must not leak in.
        $otherChapter = Chapter::factory()->for(Act::factory()->for(Book::factory()->for($project)))->create();
        Scene::factory()->for($otherChapter)->create();

        $this->assertSame([$chapter->id], $book->chapterQuery()->pluck('id')->all());
        $this->assertSame([$scene->id], $book->sceneQuery()->pluck('id')->all());
    }
}
