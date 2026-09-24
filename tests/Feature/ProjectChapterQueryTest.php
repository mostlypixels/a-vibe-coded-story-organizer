<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectChapterQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_chapters_of_every_book_in_the_project_only(): void
    {
        $project = Project::factory()->create();
        $firstBookChapter = Chapter::factory()->for(Act::factory()->for($project->books()->firstOrFail()))->create();
        $secondBookChapter = Chapter::factory()->for(Act::factory()->for(Book::factory()->for($project)))->create();

        // A chapter of another project must not leak in.
        Chapter::factory()->for(Act::factory()->for(Project::factory()->create()->books()->firstOrFail()))->create();

        $this->assertEqualsCanonicalizing(
            [$firstBookChapter->id, $secondBookChapter->id],
            $project->chapterQuery()->pluck('id')->all(),
        );
    }
}
