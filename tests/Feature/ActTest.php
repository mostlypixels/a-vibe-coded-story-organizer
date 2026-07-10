<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Project;
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
