<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use App\Support\PlotlineColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for PlotlineController.
 *
 * Project-creation invariants that seed the un-deletable main plotline live in
 * ProjectTest; here we cover the controller's own CRUD, authorization, colour
 * validation, and the is_main deletion guard.
 */
class PlotlineTest extends TestCase
{
    use RefreshDatabase;

    // --- Index -------------------------------------------------------------

    public function test_a_user_can_view_the_plotlines_index_for_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'A Named Plotline']);

        $this->actingAs($user)->get(route('projects.plotlines.index', $project))
            ->assertOk()
            ->assertSee('A Named Plotline');
    }

    public function test_the_plotlines_index_can_be_sorted_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zebra Plotline']);
        Plotline::factory()->for($project)->create(['name' => 'Apple Plotline']);

        $this->actingAs($user)->get(route('projects.plotlines.index', ['project' => $project, 'sort' => 'name', 'direction' => 'asc']))
            ->assertSeeInOrder(['Apple Plotline', 'Main plotline', 'Zebra Plotline']);
    }

    public function test_the_plotlines_index_can_be_filtered_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Plotline::factory()->for($project)->create(['name' => 'Zebra Plotline']);
        Plotline::factory()->for($project)->create(['name' => 'Apple Plotline']);

        $response = $this->actingAs($user)->get(route('projects.plotlines.index', ['project' => $project, 'search' => 'Zebra']));

        $response->assertSee('Zebra Plotline');
        $response->assertDontSee('Apple Plotline');
    }

    public function test_a_user_cannot_view_the_plotlines_index_for_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('projects.plotlines.index', $project))->assertForbidden();
    }

    // --- Create / store ----------------------------------------------------

    public function test_a_user_can_view_the_plotline_create_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.plotlines.create', $project))->assertOk();
    }

    public function test_a_user_can_add_a_plotline_to_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
            'description' => 'Some description',
            'color' => PlotlineColors::PRESETS[1],
        ]);

        $response->assertRedirect(route('projects.plotlines.index', $project));
        $this->assertSame(2, $project->plotlines()->count());
    }

    public function test_a_user_cannot_add_a_plotline_to_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
        ])->assertForbidden();
    }

    public function test_a_plotline_requires_a_valid_preset_color(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
        ])->assertSessionHasErrors('color');

        $this->actingAs($user)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
            'color' => '#000000',
        ])->assertSessionHasErrors('color');
    }

    public function test_two_plotlines_in_the_same_project_cannot_share_a_color(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $usedColor = $project->plotlines()->first()->color;

        $this->actingAs($user)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
            'color' => $usedColor,
        ])->assertSessionHasErrors('color');
    }

    public function test_the_same_color_can_be_used_across_different_projects(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $color = PlotlineColors::PRESETS[5];

        Plotline::factory()->for($projectA)->create(['color' => $color]);

        $this->actingAs($user)->post(route('projects.plotlines.store', $projectB), [
            'name' => 'A Plotline',
            'color' => $color,
        ])->assertRedirect(route('projects.plotlines.index', $projectB));

        $this->assertSame(2, $projectB->plotlines()->count());
    }

    // --- Edit / update -----------------------------------------------------

    public function test_a_user_can_view_the_plotline_edit_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create(['name' => 'Editable Plotline']);

        $this->actingAs($user)->get(route('plotlines.edit', $plotline))
            ->assertOk()
            ->assertSee('Editable Plotline');
    }

    public function test_a_user_can_update_a_plotline_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create(['name' => 'Old Name']);

        $this->actingAs($user)->put(route('plotlines.update', $plotline), [
            'name' => 'New Name',
            'color' => $plotline->color,
        ])->assertRedirect(route('projects.plotlines.index', $project));

        $this->assertSame('New Name', $plotline->fresh()->name);
    }

    public function test_saving_the_edit_form_records_a_labeled_manual_revision_for_the_changed_description(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create(['description' => 'Old description']);

        $this->actingAs($user)->put(route('plotlines.update', $plotline), [
            'name' => $plotline->name,
            'color' => $plotline->color,
            'description' => 'New description',
        ]);

        $revision = $plotline->revisions()->where('field', 'description')->latest('created_at')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionOrigin::Manual, $revision->origin);
        $this->assertNotNull($revision->label);
    }

    public function test_a_user_cannot_update_a_plotline_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = Plotline::factory()->for($project)->create();

        $this->actingAs($other)->put(route('plotlines.update', $plotline), [
            'name' => 'Hijacked',
            'color' => $plotline->color,
        ])->assertForbidden();
    }

    // --- Destroy -----------------------------------------------------------

    public function test_a_regular_plotline_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create();

        $this->actingAs($user)->delete(route('plotlines.destroy', $plotline))
            ->assertRedirect(route('projects.plotlines.index', $project));

        $this->assertNull($plotline->fresh());
    }

    public function test_the_main_plotline_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();

        $this->actingAs($user)->delete(route('plotlines.destroy', $mainPlotline))->assertForbidden();
        $this->assertNotNull($mainPlotline->fresh());
    }

    public function test_a_user_cannot_delete_a_plotline_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = Plotline::factory()->for($project)->create();

        $this->actingAs($other)->delete(route('plotlines.destroy', $plotline))->assertForbidden();
        $this->assertNotNull($plotline->fresh());
    }
}
