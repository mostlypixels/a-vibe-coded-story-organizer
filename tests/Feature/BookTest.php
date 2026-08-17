<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Book model and the invariants that hold it together: a project always has
 * one, an unnamed book borrows the project's name, and a second book ends that
 * borrowing.
 *
 * The CRUD screens and their authorization come later; this covers the model.
 */
class BookTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Every project holds at least one book
    // ---------------------------------------------------------------------

    public function test_creating_a_project_creates_exactly_one_unnamed_book(): void
    {
        $project = Project::factory()->create();

        $books = $project->books()->get();

        $this->assertCount(1, $books);
        $this->assertNull($books->first()->name);
        $this->assertSame(1, $books->first()->position);
    }

    // ---------------------------------------------------------------------
    // The name fallback
    // ---------------------------------------------------------------------

    public function test_an_unnamed_book_displays_the_project_name_and_has_no_own_name(): void
    {
        [$project, $book] = $this->projectWithBook();

        $this->assertFalse($book->hasOwnName());
        $this->assertSame($project->name, $book->displayName());
    }

    public function test_an_unnamed_book_follows_a_project_rename(): void
    {
        [$project, $book] = $this->projectWithBook();

        $project->update(['name' => 'The Lusignan Cycle']);

        $this->assertSame('The Lusignan Cycle', $book->fresh()->displayName());
    }

    public function test_a_named_book_displays_its_own_name(): void
    {
        [$project, $book] = $this->projectWithBook();
        $book->update(['name' => 'The Fountain of Thirst']);

        $this->assertTrue($book->hasOwnName());
        $this->assertSame('The Fountain of Thirst', $book->displayName());
        $this->assertNotSame($project->name, $book->displayName());
    }

    public function test_a_second_book_freezes_the_projects_name_onto_the_unnamed_first(): void
    {
        [$project, $first] = $this->projectWithBook();
        $nameAtFreeze = $project->name;

        Book::factory()->for($project)->create(['name' => 'Volume Two']);

        // The project is no longer a one-book project, so volume one needs a name
        // of its own — otherwise renaming the project to a series title renames it.
        $this->assertSame($nameAtFreeze, $first->fresh()->name);

        $project->update(['name' => 'The Lusignan Cycle']);

        $this->assertSame($nameAtFreeze, $first->fresh()->name);
        $this->assertSame($nameAtFreeze, $first->fresh()->displayName());
    }

    public function test_a_third_book_leaves_the_named_siblings_alone(): void
    {
        [$project, $first] = $this->projectWithBook();
        $second = Book::factory()->for($project)->create(['name' => 'Volume Two']);
        $frozenName = $first->fresh()->name;

        Book::factory()->for($project)->create(['name' => 'Volume Three']);

        $this->assertSame($frozenName, $first->fresh()->name);
        $this->assertSame('Volume Two', $second->fresh()->name);
    }

    public function test_the_freeze_only_reaches_the_books_of_the_same_project(): void
    {
        [, $otherProjectBook] = $this->projectWithBook();
        [$project] = $this->projectWithBook();

        Book::factory()->for($project)->create(['name' => 'Volume Two']);

        $this->assertNull($otherProjectBook->fresh()->name);
    }

    // ---------------------------------------------------------------------
    // position — per project, assigned by the creating hook
    // ---------------------------------------------------------------------

    public function test_position_auto_assigns_within_the_project(): void
    {
        [$project] = $this->projectWithBook();
        [$otherProject] = $this->projectWithBook();

        $second = Book::factory()->for($project)->create();
        $third = Book::factory()->for($project)->create();
        $otherSecond = Book::factory()->for($otherProject)->create();

        $this->assertSame(2, $second->position);
        $this->assertSame(3, $third->position);
        $this->assertSame(2, $otherSecond->position);
    }

    public function test_books_are_listed_in_position_order(): void
    {
        [$project, $first] = $this->projectWithBook();
        $second = Book::factory()->for($project)->create(['position' => 9]);
        $third = Book::factory()->for($project)->create(['position' => 4]);

        $this->assertSame(
            [$first->id, $third->id, $second->id],
            $project->books()->pluck('id')->all(),
        );
    }

    public function test_move_down_swaps_position_with_the_next_book(): void
    {
        [$project, $first] = $this->projectWithBook();
        $second = Book::factory()->for($project)->create();

        $first->moveDown();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    // ---------------------------------------------------------------------
    // Deleting — the file and total cleanup a cascade cannot do
    // ---------------------------------------------------------------------

    public function test_deleting_a_book_removes_its_cover_file(): void
    {
        Storage::fake('public');
        [$project] = $this->projectWithBook();
        $coverPath = 'book-covers/doomed-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        $book = Book::factory()->for($project)->create(['cover_image' => $coverPath]);

        $book->delete();

        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_deleting_a_book_removes_its_chapters_cover_files(): void
    {
        Storage::fake('public');
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $coverPath = 'chapter-covers/cascade-book-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        Chapter::factory()->for($act)->create(['cover_image' => $coverPath]);

        // Deleting the book cascades to its acts and their chapters at the DB
        // level (bypassing Chapter::deleting); Book::deleting must purge the
        // cover files itself.
        $book->delete();

        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_deleting_a_project_cascades_to_its_books(): void
    {
        [$project, $book] = $this->projectWithBook();

        $project->delete();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_deleting_a_book_records_the_projects_word_count(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($firstBook))->create();
        Scene::factory()->for($chapter)->create(['contents' => 'One two three']);
        $book = Book::factory()->for($project)->create();

        // Deleted first, so the assertion can only pass if Book::deleted wrote a
        // fresh row: the scenes under a book go by database cascade, firing no
        // Scene::deleted of their own.
        WordCountSnapshot::query()->delete();

        $book->delete();

        $this->assertSame(3, WordCountSnapshot::where('project_id', $project->id)->firstOrFail()->word_count);
    }
}
