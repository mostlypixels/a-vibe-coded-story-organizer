<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the Chapter resource: index, CRUD, authorization, validation
 * (including that the chosen `act_id` must belong to the same project), the
 * auto-assigned `position` invariant, and move-up/move-down reordering (scoped to
 * the owning act via HasSiblingPosition).
 */
class ChapterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Act $act, array $overrides = []): array
    {
        return array_merge([
            'act_id' => $act->id,
            'name' => 'A Quiet Chapter',
            'description' => 'A test chapter.',
        ], $overrides);
    }

    /** A scene with exactly $wordCount words, built from a repeated token (mirrors StoryTest). */
    private function sceneWithWordCount(Chapter $chapter, int $wordCount): Scene
    {
        return Scene::factory()->for($chapter)->create([
            'contents' => trim(str_repeat('word ', $wordCount)),
        ]);
    }

    public function test_the_chapters_index_lists_chapters_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        Chapter::factory()->for($act)->create(['name' => 'The Long Road']);

        $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk()
            ->assertSee('The Long Road');
    }

    public function test_the_chapters_index_shows_only_the_chapters_of_the_book_in_the_url(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        Chapter::factory()->for(Act::factory()->for($firstBook))->create(['name' => 'Volume one chapter']);
        Chapter::factory()->for(Act::factory()->for($secondBook))->create(['name' => 'Volume two chapter']);

        $this->actingAs($user)
            ->get(route('books.chapters.index', $firstBook))
            ->assertOk()
            ->assertSee('Volume one chapter')
            ->assertDontSee('Volume two chapter');
    }

    public function test_a_chapter_cannot_be_attached_to_an_act_from_another_book(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $foreignAct = Act::factory()->for(Book::factory()->for($project))->create();

        $this->actingAs($user)
            ->post(route('books.chapters.store', $firstBook), $this->validPayload($foreignAct))
            ->assertSessionHasErrors('act_id');

        $this->assertSame(0, Chapter::count());
    }

    public function test_a_user_cannot_view_chapters_of_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->get(route('books.chapters.index', $book))
            ->assertForbidden();
    }

    public function test_a_user_can_create_a_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $response = $this->actingAs($user)
            ->post(route('books.chapters.store', $book), $this->validPayload($act, ['name' => 'Chapter One']));

        $response->assertRedirect(route('books.chapters.index', $book));

        $chapter = Chapter::first();
        $this->assertNotNull($chapter);
        $this->assertSame('Chapter One', $chapter->name);
        $this->assertSame($act->id, $chapter->act_id);
    }

    public function test_chapter_positions_are_auto_assigned_sequentially_within_an_act(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $this->actingAs($user)
            ->post(route('books.chapters.store', $book), $this->validPayload($act, ['name' => 'First']));
        $this->actingAs($user)
            ->post(route('books.chapters.store', $book), $this->validPayload($act, ['name' => 'Second']));

        $this->assertSame(1, Chapter::where('name', 'First')->value('position'));
        $this->assertSame(2, Chapter::where('name', 'Second')->value('position'));
    }

    public function test_a_user_cannot_create_a_chapter_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);
        $act = Act::factory()->for($book)->create();

        $this->actingAs($other)
            ->post(route('books.chapters.store', $book), $this->validPayload($act))
            ->assertForbidden();

        $this->assertSame(0, Chapter::count());
    }

    public function test_chapter_creation_requires_a_name(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $this->actingAs($user)
            ->post(route('books.chapters.store', $book), $this->validPayload($act, ['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Chapter::count());
    }

    public function test_a_chapter_cannot_be_attached_to_an_act_from_another_project(): void
    {
        $user = User::factory()->create();
        [$ownProject, $ownBook] = $this->projectWithBook($user);
        $foreignAct = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();

        // Posting to $ownProject with an act_id that lives outside it must fail validation.
        $this->actingAs($user)
            ->post(route('books.chapters.store', $ownBook), $this->validPayload($foreignAct))
            ->assertSessionHasErrors('act_id');

        $this->assertSame(0, Chapter::count());
    }

    public function test_a_user_can_update_a_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, ['name' => 'New name']));

        $response->assertRedirect(route('books.chapters.index', $book));
        $this->assertSame('New name', $chapter->fresh()->name);
    }

    public function test_saving_the_edit_form_records_a_labeled_manual_revision_for_the_changed_description(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create(['description' => 'Old description']);

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, ['description' => 'New description']));

        $revision = $chapter->revisions()->where('field', 'description')->latest('created_at')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionOrigin::Manual, $revision->origin);
        $this->assertNotNull($revision->label);
    }

    public function test_a_chapter_can_be_moved_to_another_act_in_the_same_project(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $sourceAct = Act::factory()->for($book)->create();
        $targetAct = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($sourceAct)->create();

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($targetAct))
            ->assertRedirect(route('books.chapters.index', $book));

        $this->assertSame($targetAct->id, $chapter->fresh()->act_id);
    }

    public function test_a_user_cannot_update_another_users_chapter(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($owner)))->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Untouched']);

        $this->actingAs($other)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, ['name' => 'Hacked']))
            ->assertForbidden();

        $this->assertSame('Untouched', $chapter->fresh()->name);
    }

    public function test_a_user_can_delete_a_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter))
            ->assertRedirect(route('books.chapters.index', $book));

        $this->assertNull($chapter->fresh());
    }

    public function test_a_user_cannot_delete_another_users_chapter(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($owner)))->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($other)
            ->delete(route('chapters.destroy', $chapter))
            ->assertForbidden();

        $this->assertNotNull($chapter->fresh());
    }

    // ---------------------------------------------------------------------
    // Delete with "move scenes elsewhere, or cascade" (data-loss-warnings)
    // ---------------------------------------------------------------------

    public function test_deleting_a_chapter_with_no_scenes_keeps_the_plain_confirmation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        // The edit page shows the original unqualified confirm(), no move-or-delete dialog.
        $this->actingAs($user)
            ->get(route('chapters.edit', $chapter))
            ->assertOk()
            ->assertSee('Are you sure you want to delete this chapter?')
            ->assertDontSee('name="move_children_to"', false);

        // And a bare DELETE (no move_children_to) still deletes normally.
        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter))
            ->assertRedirect(route('books.chapters.index', $book));

        $this->assertNull($chapter->fresh());
    }

    public function test_edit_page_offers_delete_only_when_the_chapter_has_scenes_but_no_destination(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->count(3)->create();

        // Only chapter in the project → the dialog renders, but with the informational
        // "delete everything" line and no destination <select>.
        $this->actingAs($user)
            ->get(route('chapters.edit', $chapter))
            ->assertOk()
            ->assertSee('This will also delete')
            ->assertSee('3 scenes')
            ->assertDontSee('name="move_children_to"', false);
    }

    public function test_edit_page_offers_the_move_picker_when_another_chapter_exists(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Chapter::factory()->for($act)->create(['name' => 'Elsewhere']);
        Scene::factory()->for($chapter)->count(2)->create();

        $this->actingAs($user)
            ->get(route('chapters.edit', $chapter))
            ->assertOk()
            ->assertSee('name="move_children_to"', false)
            ->assertSee('Elsewhere');
    }

    public function test_deleting_a_chapter_without_a_destination_cascades_as_before(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        // A sibling chapter exists, but the user chose "delete everything" (no move_children_to).
        Chapter::factory()->for($act)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter))
            ->assertRedirect(route('books.chapters.index', $book));

        // The chapter and its scenes are gone via the FK cascade, exactly as before.
        $this->assertNull($chapter->fresh());
        $this->assertNull($scene->fresh());
    }

    public function test_deleting_a_chapter_can_move_its_scenes_to_another_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $source = Chapter::factory()->for($act)->create();
        $destination = Chapter::factory()->for($act)->create();

        // Destination already has two scenes (positions 1, 2).
        Scene::factory()->for($destination)->create(['position' => 1]);
        Scene::factory()->for($destination)->create(['position' => 2]);

        // Source scenes in a known order.
        $first = Scene::factory()->for($source)->create(['position' => 1, 'name' => 'Source First']);
        $second = Scene::factory()->for($source)->create(['position' => 2, 'name' => 'Source Second']);

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $source), ['move_children_to' => $destination->id])
            ->assertRedirect(route('books.chapters.index', $book));

        // Source chapter is gone; the moved scenes are NOT deleted.
        $this->assertNull($source->fresh());
        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());

        // Every moved scene now belongs to the destination.
        $this->assertSame($destination->id, $first->fresh()->chapter_id);
        $this->assertSame($destination->id, $second->fresh()->chapter_id);

        // Appended after the destination's existing max position (2), in original order.
        $this->assertSame(3, $first->fresh()->position);
        $this->assertSame(4, $second->fresh()->position);
    }

    public function test_moved_scenes_never_collide_positions_in_the_destination(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $source = Chapter::factory()->for($act)->create();
        $destination = Chapter::factory()->for($act)->create();

        Scene::factory()->for($destination)->create(['position' => 1]);
        Scene::factory()->for($source)->count(3)->sequence(
            ['position' => 1],
            ['position' => 2],
            ['position' => 3],
        )->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $source), ['move_children_to' => $destination->id]);

        $positions = Scene::where('chapter_id', $destination->id)->pluck('position')->all();

        $this->assertSame($positions, array_values(array_unique($positions)));
    }

    public function test_move_children_to_must_be_another_chapter_in_the_same_project(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        // A destination in a different project is rejected.
        $foreignChapter = Chapter::factory()->for(Act::factory()->for(Book::factory()->for(Project::factory()->for($user))))->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter), ['move_children_to' => $foreignChapter->id])
            ->assertSessionHasErrors('move_children_to');

        $this->assertNotNull($chapter->fresh());

        // The chapter's own id as a destination is rejected (Rule::notIn).
        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter), ['move_children_to' => $chapter->id])
            ->assertSessionHasErrors('move_children_to');

        $this->assertNotNull($chapter->fresh());
    }

    public function test_a_non_owner_cannot_delete_or_move_a_chapters_scenes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($owner)))->create();
        $chapter = Chapter::factory()->for($act)->create();
        $destination = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $this->actingAs($other)
            ->delete(route('chapters.destroy', $chapter), ['move_children_to' => $destination->id])
            ->assertForbidden();

        $this->assertNotNull($chapter->fresh());
    }

    // ---------------------------------------------------------------------
    // Cover image — upload / replace / remove / validation / cleanup
    // ---------------------------------------------------------------------

    public function test_uploading_a_cover_sets_the_chapter_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ]))
            ->assertRedirect(route('books.chapters.index', $act->book));

        $chapter->refresh();
        $this->assertNotNull($chapter->cover_image);
        Storage::disk('public')->assertExists($chapter->cover_image);
    }

    public function test_replacing_the_cover_deletes_the_old_file_and_stores_the_new_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $oldPath = 'chapter-covers/old-cover.jpg';
        Storage::disk('public')->put($oldPath, 'contents');
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => $oldPath]);

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'cover_image' => UploadedFile::fake()->image('new-cover.jpg'),
            ]));

        $chapter->refresh();
        $this->assertNotSame($oldPath, $chapter->cover_image);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($chapter->cover_image);
    }

    public function test_removing_the_cover_clears_the_column_and_deletes_the_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $oldPath = 'chapter-covers/old-cover.jpg';
        Storage::disk('public')->put($oldPath, 'contents');
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => $oldPath]);

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'remove_cover_image' => '1',
            ]));

        $this->assertNull($chapter->fresh()->cover_image);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_updating_a_chapter_with_an_invalid_cover_fails_validation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $chapter = Chapter::factory()->for($act)->create();

        // A non-image file (wrong type).
        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'cover_image' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('cover_image');

        // An oversized image (over the 5 MB / 5120 KB cover limit).
        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'cover_image' => UploadedFile::fake()->image('huge.jpg')->size(6000),
            ]))
            ->assertSessionHasErrors('cover_image');
    }

    public function test_deleting_a_chapter_removes_its_cover_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $coverPath = 'chapter-covers/doomed-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        $chapter = Chapter::factory()->for($act)->create(['cover_image' => $coverPath]);

        $this->actingAs($user)->delete(route('chapters.destroy', $chapter));

        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_deleting_an_act_removes_its_chapters_cover_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $coverPath = 'chapter-covers/cascade-act-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        Chapter::factory()->for($act)->create(['cover_image' => $coverPath]);

        // Deleting the act cascades to its chapters at the DB level (bypassing
        // Chapter::deleting); Act::deleting must purge the cover file itself.
        $act->delete();

        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_deleting_a_project_removes_its_chapters_cover_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $coverPath = 'chapter-covers/cascade-project-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        Chapter::factory()->for($act)->create(['cover_image' => $coverPath]);

        // The project cascade drops act + chapter rows via the FK, bypassing both
        // Act::deleting and Chapter::deleting; Project::deleting must purge the
        // surviving chapters' cover files project-wide.
        $project->delete();

        Storage::disk('public')->assertMissing($coverPath);
    }

    // ---------------------------------------------------------------------
    // Reordering — the swap logic extracted to HasSiblingPosition
    // ---------------------------------------------------------------------

    public function test_move_down_swaps_position_with_the_next_chapter(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $second = Chapter::factory()->for($act)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('chapters.move-down', $first))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_move_up_swaps_position_with_the_previous_chapter(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $second = Chapter::factory()->for($act)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('chapters.move-up', $second))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_chapters_only_swap_with_siblings_in_the_same_act(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actOne = Act::factory()->for($book)->create();
        $actTwo = Act::factory()->for($book)->create();

        $chapterOne = Chapter::factory()->for($actOne)->create(['position' => 1]);
        $chapterTwo = Chapter::factory()->for($actTwo)->create(['position' => 2]);

        // The first act's only chapter finds no sibling to swap with, so the chapter
        // in the OTHER act is never touched (the scope column matters).
        $this->actingAs($user)->patch(route('chapters.move-down', $chapterOne))->assertRedirect();

        $this->assertSame(1, $chapterOne->fresh()->position);
        $this->assertSame(2, $chapterTwo->fresh()->position);
    }

    public function test_a_user_cannot_reorder_another_users_chapter(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($owner)))->create();
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);
        Chapter::factory()->for($act)->create(['position' => 2]);

        $this->actingAs($other)
            ->patch(route('chapters.move-down', $chapter))
            ->assertForbidden();

        $this->assertSame(1, $chapter->fresh()->position);
    }

    public function test_the_edit_page_links_to_the_chapters_revision_history(): void
    {
        // The Actions card carries the entity-level History link. The closing
        // quote prevents a match on the per-field `?field=` icon link beside
        // the description editor.
        $user = User::factory()->create();
        $act = Act::factory()->for(Book::factory()->for(Project::factory()->for($user)))->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->get(route('chapters.edit', $chapter))
            ->assertOk()
            ->assertSee(
                'href="'.route('revisions.index', ['entity' => 'chapter', 'id' => $chapter->id]).'"',
                false,
            );
    }

    // --- Index ordering by story order -----------------------------------

    /**
     * Two acts, one chapter each, then act B is moved above act A. The `#` column
     * must follow the story, so B's chapter comes first — the previous
     * `orderBy('act_id')` grouping kept A first forever, because act_id only
     * matches story order until someone reorders an act.
     */
    public function test_the_chapters_index_orders_by_story_order_after_an_act_is_reordered(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actA = Act::factory()->for($book)->create(['name' => 'Act A', 'position' => 1]);
        $actB = Act::factory()->for($book)->create(['name' => 'Act B', 'position' => 2]);
        Chapter::factory()->for($actA)->create(['name' => 'Chapter from A', 'position' => 1]);
        Chapter::factory()->for($actB)->create(['name' => 'Chapter from B', 'position' => 1]);

        $this->actingAs($user)->patch(route('acts.move-up', $actB));

        $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'sort' => 'position']))
            ->assertOk()
            ->assertSeeInOrder(['Chapter from B', 'Chapter from A']);
    }

    /**
     * Descending must reverse *every* ordering key, so the list reads as the story
     * backwards — not acts ascending with their chapters reversed inside them.
     */
    public function test_the_chapters_index_reverses_the_whole_story_when_sorted_descending(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actOne)->create(['name' => 'Chapter One', 'position' => 1]);
        Chapter::factory()->for($actOne)->create(['name' => 'Chapter Two', 'position' => 2]);
        Chapter::factory()->for($actTwo)->create(['name' => 'Chapter Three', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'sort' => 'position', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Chapter Three', 'Chapter Two', 'Chapter One']);
    }

    /**
     * `acts` carries `name` and `position` columns of its own, so the join added for
     * story order makes an unqualified `name` ambiguous. This covers both places it
     * appears: the search filter and `?sort=name`.
     */
    public function test_the_chapters_index_still_sorts_and_searches_by_name(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'The Act']);
        Chapter::factory()->for($act)->create(['name' => 'Zebra', 'position' => 1]);
        Chapter::factory()->for($act)->create(['name' => 'Antelope', 'position' => 2]);

        $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'sort' => 'name']))
            ->assertOk()
            ->assertSeeInOrder(['Antelope', 'Zebra']);

        $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'search' => 'Zeb']))
            ->assertOk()
            ->assertSee('Zebra')
            ->assertDontSee('Antelope');
    }

    /**
     * `withCount`/`withSum` add `chapters.*` themselves, and a `select()` after them
     * would silently drop their subquery aliases. Asserting the aggregates survive
     * the join guards that column-list order.
     */
    public function test_the_chapters_index_keeps_its_aggregates_with_the_act_join(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $this->sceneWithWordCount($chapter, 613);
        $this->sceneWithWordCount($chapter, 402);

        $chapters = $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk()
            ->viewData('chapters');

        $this->assertSame(2, $chapters->first()->scenes_count);
        $this->assertSame(1015, (int) $chapters->first()->word_count);
    }

    // --- Continuous numbering --------------------------------------------

    /**
     * The trimmed, tag-stripped text of the `$index`-th `<td>` (0-based) in the row
     * whose name is `$rowName` — scoped to a single `<tr>...</tr>` block so it
     * can't be fooled by an id or word count elsewhere on the page matching.
     */
    private function columnCellFor(string $html, string $rowName, int $index): string
    {
        preg_match('/<tr[^>]*>((?:(?!<\/tr>).)*?'.preg_quote($rowName, '/').'(?:(?!<\/tr>).)*?)<\/tr>/s', $html, $rowMatch);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $rowMatch[1] ?? '', $cellMatches);

        return isset($cellMatches[1][$index]) ? trim(strip_tags($cellMatches[1][$index])) : '';
    }

    /** The '#' column (index 0) is the same across every chapters-index row. */
    private function numberColumnFor(string $html, string $rowName): string
    {
        return $this->columnCellFor($html, $rowName, 0);
    }

    public function test_the_chapters_index_number_column_is_continuous_not_the_per_act_position(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actOne)->create(['name' => 'Opening', 'position' => 1]);
        // Gappy per-act position (5): a regression back to `$chapter->position`
        // would render this row's '#' cell as "5" instead of "2".
        Chapter::factory()->for($actTwo)->create(['name' => 'Closing', 'position' => 5]);

        $html = $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'sort' => 'position']))
            ->assertOk()
            ->getContent();

        $this->assertSame('2', $this->numberColumnFor($html, 'Closing'));
    }

    /**
     * Filtering the list to one act must never renumber it: the map is built from
     * the whole project, so the first (and only) row shown still reads its true,
     * book-wide number.
     */
    public function test_the_chapters_index_numbers_stay_book_wide_when_filtered_to_one_act(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actOne)->create(['position' => 1]);
        Chapter::factory()->for($actOne)->create(['position' => 2]);
        Chapter::factory()->for($actTwo)->create(['name' => 'Fresh Start', 'position' => 1]);

        $html = $this->actingAs($user)
            ->get(route('books.chapters.index', ['book' => $book, 'act' => $actTwo->id, 'sort' => 'position']))
            ->assertOk()
            ->getContent();

        $this->assertSame('3', $this->numberColumnFor($html, 'Fresh Start'));
    }

    /**
     * The edit page's position hint shows both the continuous, book-wide number
     * and the chapter's rank among its act's siblings — the latter a gap-free rank,
     * not the raw (possibly gappy) `position` column.
     */
    public function test_the_edit_page_shows_the_continuous_number_and_position_within_the_act(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actOne = Act::factory()->for($book)->create(['position' => 1]);
        $actTwo = Act::factory()->for($book)->create(['position' => 2]);
        Chapter::factory()->for($actOne)->create(['position' => 1]);
        Chapter::factory()->for($actTwo)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($actTwo)->create(['position' => 2]);
        Chapter::factory()->for($actTwo)->create(['position' => 3]);

        // Continuous number 3 (1 from act one, then 2nd of act two's three
        // chapters); rank 2 of 3 within act two, which is itself act number 2.
        $this->actingAs($user)
            ->get(route('chapters.edit', $chapter))
            ->assertOk()
            ->assertSee('Chapter 3 — 2 of 3 in Act 2. Use the move up/down buttons on the list to reorder.');
    }

    // --- Word count column -----------------------------------------------

    public function test_the_chapters_index_shows_each_chapters_total_word_count(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapterA = Chapter::factory()->for($act)->create(['name' => 'Chapter A']);
        $chapterB = Chapter::factory()->for($act)->create(['name' => 'Chapter B']);

        // Totals deliberately far outside the range a position, id, or the
        // scenes_count column already on this row could produce, so the assertion
        // can only be satisfied by the summed word count.
        $this->sceneWithWordCount($chapterA, 613);
        $this->sceneWithWordCount($chapterA, 402); // chapter A total: 1,015
        $this->sceneWithWordCount($chapterB, 47);

        $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk()
            ->assertSee('1,015 words')
            ->assertSee('47 words');
    }

    public function test_the_chapters_index_footer_totals_words_across_every_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapterA = Chapter::factory()->for($act)->create();
        $chapterB = Chapter::factory()->for($act)->create();
        $this->sceneWithWordCount($chapterA, 613);
        $this->sceneWithWordCount($chapterB, 449); // grand total: 1,062, distinct from any row

        $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk()
            ->assertSee('Total')
            ->assertSee('1,062 words');
    }

    public function test_a_chapter_with_no_scenes_shows_zero_words_on_the_index(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk()
            ->assertSee('0 words');
    }

    /**
     * withSum() must fold every chapter's total into the same query as the row
     * list itself. A naive per-row sum() (in the controller loop or the view)
     * would issue one query per chapter — 10 here instead of 1 — so this counts
     * queries against the scenes table specifically, the same isolation
     * StoryTest's own N+1 test uses.
     */
    public function test_the_chapters_index_issues_one_grouped_query_for_word_counts(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        foreach (range(1, 10) as $chapterNumber) {
            $chapter = Chapter::factory()->for($act)->create();
            $this->sceneWithWordCount($chapter, $chapterNumber * 10);
        }

        $sceneQueries = [];
        DB::listen(function ($query) use (&$sceneQueries) {
            if (str_contains($query->sql, '"scenes"')) {
                $sceneQueries[] = $query->sql;
            }
        });

        $this->actingAs($user)
            ->get(route('books.chapters.index', $book))
            ->assertOk();

        // 1 for the withSum() word-count aggregate, 1 more for StoryNumbering::
        // forBook()'s own eager load of the whole act -> chapter -> scene tree.
        // Both stay O(1) per page load, not O(chapters), so the N+1 this test
        // guards against is still absent.
        $this->assertCount(2, $sceneQueries);
    }
}
