<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Act resource: index, CRUD, authorization, validation,
 * the auto-assigned `position` invariant, and the move-up/move-down reordering
 * (the swap logic that lives in HasSiblingPosition, scoped here to the project).
 */
class ActTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_acts_index_lists_acts_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Act::factory()->for($project)->create(['name' => 'The Gathering Storm']);

        $this->actingAs($user)
            ->get(route('projects.acts.index', $project))
            ->assertOk()
            ->assertSee('The Gathering Storm');
    }

    public function test_a_user_cannot_view_acts_of_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.acts.index', $project))
            ->assertForbidden();
    }

    public function test_a_user_can_create_an_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.acts.store', $project), [
            'name' => 'Act One',
            'description' => 'The beginning.',
        ]);

        $response->assertRedirect(route('projects.acts.index', $project));

        $act = Act::first();
        $this->assertNotNull($act);
        $this->assertSame('Act One', $act->name);
        $this->assertSame($project->id, $act->project_id);
    }

    public function test_act_positions_are_auto_assigned_sequentially_within_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('projects.acts.store', $project), ['name' => 'First']);
        $this->actingAs($user)
            ->post(route('projects.acts.store', $project), ['name' => 'Second']);

        $this->assertSame(1, Act::where('name', 'First')->value('position'));
        $this->assertSame(2, Act::where('name', 'Second')->value('position'));
    }

    public function test_a_user_cannot_create_an_act_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post(route('projects.acts.store', $project), ['name' => 'Sneaky act'])
            ->assertForbidden();

        $this->assertSame(0, Act::count());
    }

    public function test_act_creation_requires_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('projects.acts.store', $project), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Act::count());
    }

    public function test_a_user_can_update_an_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->put(route('acts.update', $act), [
            'name' => 'New name',
            'description' => 'Rewritten.',
        ]);

        $response->assertRedirect(route('projects.acts.index', $project));
        $this->assertSame('New name', $act->fresh()->name);
    }

    public function test_a_user_cannot_update_another_users_act(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($owner))->create(['name' => 'Untouched']);

        $this->actingAs($other)
            ->put(route('acts.update', $act), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Untouched', $act->fresh()->name);
    }

    public function test_a_user_can_delete_an_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $this->actingAs($user)
            ->delete(route('acts.destroy', $act))
            ->assertRedirect(route('projects.acts.index', $project));

        $this->assertNull($act->fresh());
    }

    public function test_a_user_cannot_delete_another_users_act(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = Act::factory()->for(Project::factory()->for($owner))->create();

        $this->actingAs($other)
            ->delete(route('acts.destroy', $act))
            ->assertForbidden();

        $this->assertNotNull($act->fresh());
    }

    // ---------------------------------------------------------------------
    // Delete with "move chapters elsewhere, or cascade" (data-loss-warnings)
    // ---------------------------------------------------------------------

    public function test_deleting_an_act_with_no_chapters_keeps_the_plain_confirmation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        // The edit page shows the original unqualified confirm(), no move-or-delete dialog.
        $this->actingAs($user)
            ->get(route('acts.edit', $act))
            ->assertOk()
            ->assertSee('Are you sure you want to delete this act?')
            ->assertDontSee('name="move_children_to"', false);

        // And a bare DELETE (no move_children_to) still deletes normally.
        $this->actingAs($user)
            ->delete(route('acts.destroy', $act))
            ->assertRedirect(route('projects.acts.index', $project));

        $this->assertNull($act->fresh());
    }

    public function test_edit_page_offers_delete_only_when_the_act_has_chapters_but_no_destination(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapter)->count(3)->create();

        // Only act in the project → the dialog renders, but with the informational
        // "delete everything" line and no destination <select>.
        $this->actingAs($user)
            ->get(route('acts.edit', $act))
            ->assertOk()
            ->assertSee('This will also delete')
            ->assertSee('1 chapter')
            ->assertSee('3 scenes')
            ->assertDontSee('name="move_children_to"', false);
    }

    public function test_edit_page_offers_the_move_picker_when_another_act_exists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        Act::factory()->for($project)->create(['name' => 'Elsewhere']);
        Chapter::factory()->for($act)->count(2)->create();

        $this->actingAs($user)
            ->get(route('acts.edit', $act))
            ->assertOk()
            ->assertSee('name="move_children_to"', false)
            ->assertSee('Elsewhere');
    }

    public function test_deleting_an_act_without_a_destination_cascades_as_before(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // A sibling act exists, but the user chose "delete everything" (no move_children_to).
        Act::factory()->for($project)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->delete(route('acts.destroy', $act))
            ->assertRedirect(route('projects.acts.index', $project));

        // The whole subtree is gone via the FK cascade, exactly as before this feature.
        $this->assertNull($act->fresh());
        $this->assertNull($chapter->fresh());
        $this->assertNull($scene->fresh());
    }

    public function test_deleting_an_act_can_move_its_chapters_to_another_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $source = Act::factory()->for($project)->create();
        $destination = Act::factory()->for($project)->create();

        // Destination already has two chapters (positions 1, 2).
        Chapter::factory()->for($destination)->create(['position' => 1]);
        Chapter::factory()->for($destination)->create(['position' => 2]);

        // Source chapters in a known order.
        $first = Chapter::factory()->for($source)->create(['position' => 1, 'name' => 'Source First']);
        $second = Chapter::factory()->for($source)->create(['position' => 2, 'name' => 'Source Second']);
        $scene = Scene::factory()->for($first)->create();

        $this->actingAs($user)
            ->delete(route('acts.destroy', $source), ['move_children_to' => $destination->id])
            ->assertRedirect(route('projects.acts.index', $project));

        // Source act is gone; the moved chapters (and their scenes) are NOT deleted.
        $this->assertNull($source->fresh());
        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());
        $this->assertNotNull($scene->fresh());

        // Every moved chapter now belongs to the destination.
        $this->assertSame($destination->id, $first->fresh()->act_id);
        $this->assertSame($destination->id, $second->fresh()->act_id);

        // Appended after the destination's existing max position (2), in original order.
        $this->assertSame(3, $first->fresh()->position);
        $this->assertSame(4, $second->fresh()->position);
    }

    public function test_moved_chapters_never_collide_positions_in_the_destination(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $source = Act::factory()->for($project)->create();
        $destination = Act::factory()->for($project)->create();

        Chapter::factory()->for($destination)->create(['position' => 1]);
        Chapter::factory()->for($source)->count(3)->sequence(
            ['position' => 1],
            ['position' => 2],
            ['position' => 3],
        )->create();

        $this->actingAs($user)
            ->delete(route('acts.destroy', $source), ['move_children_to' => $destination->id]);

        $positions = Chapter::where('act_id', $destination->id)->pluck('position')->all();

        $this->assertSame($positions, array_values(array_unique($positions)));
    }

    public function test_move_children_to_must_be_another_act_in_the_same_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        Chapter::factory()->for($act)->create();

        // A destination in a different project is rejected.
        $foreignAct = Act::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->delete(route('acts.destroy', $act), ['move_children_to' => $foreignAct->id])
            ->assertSessionHasErrors('move_children_to');

        $this->assertNotNull($act->fresh());

        // The act's own id as a destination is rejected (Rule::notIn).
        $this->actingAs($user)
            ->delete(route('acts.destroy', $act), ['move_children_to' => $act->id])
            ->assertSessionHasErrors('move_children_to');

        $this->assertNotNull($act->fresh());
    }

    public function test_a_non_owner_cannot_delete_or_move_an_acts_chapters(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create();
        $destination = Act::factory()->for($project)->create();
        Chapter::factory()->for($act)->create();

        $this->actingAs($other)
            ->delete(route('acts.destroy', $act), ['move_children_to' => $destination->id])
            ->assertForbidden();

        $this->assertNotNull($act->fresh());
    }

    // ---------------------------------------------------------------------
    // Reordering — the swap logic extracted to HasSiblingPosition
    // ---------------------------------------------------------------------

    public function test_move_down_swaps_position_with_the_next_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $first = Act::factory()->for($project)->create(['position' => 1]);
        $second = Act::factory()->for($project)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('acts.move-down', $first))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_move_up_swaps_position_with_the_previous_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $first = Act::factory()->for($project)->create(['position' => 1]);
        $second = Act::factory()->for($project)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('acts.move-up', $second))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_move_down_at_the_end_of_the_project_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $first = Act::factory()->for($project)->create(['position' => 1]);
        $last = Act::factory()->for($project)->create(['position' => 2]);

        $this->actingAs($user)->patch(route('acts.move-down', $last))->assertRedirect();

        // Nothing to swap with — positions are untouched.
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $last->fresh()->position);
    }

    public function test_acts_only_swap_with_siblings_in_the_same_project(): void
    {
        $user = User::factory()->create();
        $projectOne = Project::factory()->for($user)->create();
        $projectTwo = Project::factory()->for($user)->create();

        $actOne = Act::factory()->for($projectOne)->create(['position' => 1]);
        $actTwo = Act::factory()->for($projectTwo)->create(['position' => 2]);

        // The first project's only act finds no sibling to swap with, so the act
        // in the OTHER project is never touched (the scope column matters).
        $this->actingAs($user)->patch(route('acts.move-down', $actOne))->assertRedirect();

        $this->assertSame(1, $actOne->fresh()->position);
        $this->assertSame(2, $actTwo->fresh()->position);
    }

    public function test_a_user_cannot_reorder_another_users_act(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $act = Act::factory()->for($project)->create(['position' => 1]);
        Act::factory()->for($project)->create(['position' => 2]);

        $this->actingAs($other)
            ->patch(route('acts.move-down', $act))
            ->assertForbidden();

        $this->assertSame(1, $act->fresh()->position);
    }
}
