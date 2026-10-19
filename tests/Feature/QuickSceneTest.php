<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

/** The Write page "New scene" dialog and its JSON endpoint. */
class QuickSceneTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Book, Chapter} */
    private function ownedChapter(): array
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        return [$user, $book, $chapter];
    }

    public function test_the_owner_creates_a_draft_scene_at_the_end_of_the_chapter(): void
    {
        [$user, $book, $chapter] = $this->ownedChapter();
        Scene::factory()->for($chapter)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 2]);

        $response = $this->actingAs($user)->postJson(route('books.scenes.quick-store', $book), [
            'chapter_id' => $chapter->id,
            'name' => 'A Lady at the Fountain',
        ]);

        $scene = Scene::where('name', 'A Lady at the Fountain')->sole();

        $response->assertCreated()->assertExactJson(['url' => route('scenes.write', $scene)]);
        $this->assertSame($chapter->id, $scene->chapter_id);
        $this->assertSame(SceneStatus::Draft, $scene->status);
        $this->assertSame(3, $scene->position);
    }

    public function test_a_non_owner_gets_403_and_creates_nothing(): void
    {
        [, $book, $chapter] = $this->ownedChapter();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->postJson(route('books.scenes.quick-store', $book), [
            'chapter_id' => $chapter->id,
            'name' => 'Intruder',
        ])->assertForbidden();

        $this->assertSame(0, Scene::count());
    }

    public function test_a_chapter_from_another_book_is_rejected(): void
    {
        [$user, $book] = $this->ownedChapter();
        $otherBook = Book::factory()->for($book->project)->create();
        $otherChapter = Chapter::factory()->for(Act::factory()->for($otherBook))->create();

        $this->actingAs($user)->postJson(route('books.scenes.quick-store', $book), [
            'chapter_id' => $otherChapter->id,
            'name' => 'Wrong book',
        ])->assertStatus(422)->assertJsonValidationErrors('chapter_id');

        $this->assertSame(0, Scene::count());
    }

    public function test_an_empty_name_is_rejected(): void
    {
        [$user, $book, $chapter] = $this->ownedChapter();

        $this->actingAs($user)->postJson(route('books.scenes.quick-store', $book), [
            'chapter_id' => $chapter->id,
            'name' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(0, Scene::count());
    }

    public function test_the_write_page_shows_the_button_and_the_book_chapters(): void
    {
        [$user, $book, $chapter] = $this->ownedChapter();
        $other = Chapter::factory()->for($chapter->act)->create(['name' => 'The Second Spring']);
        $scene = Scene::factory()->for($chapter)->create();

        $foreignProject = Project::factory()->for($user)->create();
        $foreign = Chapter::factory()->for(Act::factory()->for($foreignProject->books()->first()))->create(['name' => 'Elsewhere']);

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('data-editor-new-scene', false)
            ->assertSee('url: '.Js::from(route('books.scenes.quick-store', $book)), false)
            ->assertSee('chapterId: '.$chapter->id, false)
            ->assertSee('<option value="'.$other->id.'">', false)
            ->assertSee('The Second Spring')
            ->assertDontSee('<option value="'.$foreign->id.'">', false);
    }
}
