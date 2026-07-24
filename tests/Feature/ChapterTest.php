<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_the_chapters_index_lists_chapters_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        Chapter::factory()->for($act)->create(['name' => 'The Long Road']);

        $this->actingAs($user)
            ->get(route('projects.chapters.index', $project))
            ->assertOk()
            ->assertSee('The Long Road');
    }

    public function test_a_user_cannot_view_chapters_of_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.chapters.index', $project))
            ->assertForbidden();
    }

    public function test_a_user_can_create_a_chapter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $response = $this->actingAs($user)
            ->post(route('projects.chapters.store', $project), $this->validPayload($act, ['name' => 'Chapter One']));

        $response->assertRedirect(route('projects.chapters.index', $project));

        $chapter = Chapter::first();
        $this->assertNotNull($chapter);
        $this->assertSame('Chapter One', $chapter->name);
        $this->assertSame($act->id, $chapter->act_id);
    }

    public function test_chapter_positions_are_auto_assigned_sequentially_within_an_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('projects.chapters.store', $project), $this->validPayload($act, ['name' => 'First']));
        $this->actingAs($user)
            ->post(route('projects.chapters.store', $project), $this->validPayload($act, ['name' => 'Second']));

        $this->assertSame(1, Chapter::where('name', 'First')->value('position'));
        $this->assertSame(2, Chapter::where('name', 'Second')->value('position'));
    }

    public function test_a_user_cannot_create_a_chapter_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create();

        $this->actingAs($other)
            ->post(route('projects.chapters.store', $project), $this->validPayload($act))
            ->assertForbidden();

        $this->assertSame(0, Chapter::count());
    }

    public function test_chapter_creation_requires_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('projects.chapters.store', $project), $this->validPayload($act, ['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Chapter::count());
    }

    public function test_a_chapter_cannot_be_attached_to_an_act_from_another_project(): void
    {
        $user = User::factory()->create();
        $ownProject = Project::factory()->for($user)->create();
        $foreignAct = Act::factory()->for(Project::factory()->for($user))->create();

        // Posting to $ownProject with an act_id that lives outside it must fail validation.
        $this->actingAs($user)
            ->post(route('projects.chapters.store', $ownProject), $this->validPayload($foreignAct))
            ->assertSessionHasErrors('act_id');

        $this->assertSame(0, Chapter::count());
    }

    public function test_a_user_can_update_a_chapter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, ['name' => 'New name']));

        $response->assertRedirect(route('projects.chapters.index', $project));
        $this->assertSame('New name', $chapter->fresh()->name);
    }

    public function test_a_chapter_can_be_moved_to_another_act_in_the_same_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $sourceAct = Act::factory()->for($project)->create();
        $targetAct = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($sourceAct)->create();

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($targetAct))
            ->assertRedirect(route('projects.chapters.index', $project));

        $this->assertSame($targetAct->id, $chapter->fresh()->act_id);
    }

    public function test_a_user_cannot_update_another_users_chapter(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($owner))->create();
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Untouched']);

        $this->actingAs($other)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, ['name' => 'Hacked']))
            ->assertForbidden();

        $this->assertSame('Untouched', $chapter->fresh()->name);
    }

    public function test_a_user_can_delete_a_chapter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter))
            ->assertRedirect(route('projects.chapters.index', $project));

        $this->assertNull($chapter->fresh());
    }

    public function test_a_user_cannot_delete_another_users_chapter(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($owner))->create();
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
            ->assertRedirect(route('projects.chapters.index', $project));

        $this->assertNull($chapter->fresh());
    }

    public function test_edit_page_offers_delete_only_when_the_chapter_has_scenes_but_no_destination(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        // A sibling chapter exists, but the user chose "delete everything" (no move_children_to).
        Chapter::factory()->for($act)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter))
            ->assertRedirect(route('projects.chapters.index', $project));

        // The chapter and its scenes are gone via the FK cascade, exactly as before.
        $this->assertNull($chapter->fresh());
        $this->assertNull($scene->fresh());
    }

    public function test_deleting_a_chapter_can_move_its_scenes_to_another_chapter(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

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
            ->assertRedirect(route('projects.chapters.index', $project));

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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        // A destination in a different project is rejected.
        $foreignChapter = Chapter::factory()->for(Act::factory()->for(Project::factory()->for($user)))->create();

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
        $act = Act::factory()->for(Project::factory()->for($owner))->create();
        $chapter = Chapter::factory()->for($act)->create();
        $destination = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->create();

        $this->actingAs($other)
            ->delete(route('chapters.destroy', $chapter), ['move_children_to' => $destination->id])
            ->assertForbidden();

        $this->assertNotNull($chapter->fresh());
    }

    // ---------------------------------------------------------------------
    // Cover image (task 07) — upload / replace / remove / validation / cleanup
    // ---------------------------------------------------------------------

    public function test_uploading_a_cover_sets_the_chapter_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($user))->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->actingAs($user)
            ->put(route('chapters.update', $chapter), $this->validPayload($act, [
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ]))
            ->assertRedirect(route('projects.chapters.index', $act->project));

        $chapter->refresh();
        $this->assertNotNull($chapter->cover_image);
        Storage::disk('public')->assertExists($chapter->cover_image);
    }

    public function test_replacing_the_cover_deletes_the_old_file_and_stores_the_new_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $act = Act::factory()->for(Project::factory()->for($user))->create();
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
        $project = Project::factory()->for($user)->create();
        $actOne = Act::factory()->for($project)->create();
        $actTwo = Act::factory()->for($project)->create();

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
        $act = Act::factory()->for(Project::factory()->for($owner))->create();
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);
        Chapter::factory()->for($act)->create(['position' => 2]);

        $this->actingAs($other)
            ->patch(route('chapters.move-down', $chapter))
            ->assertForbidden();

        $this->assertSame(1, $chapter->fresh()->position);
    }
}
