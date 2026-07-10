<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
