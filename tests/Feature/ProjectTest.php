<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use App\Support\PlotlineColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_the_authenticated_users_projects_alphabetically(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Zebra']);
        Project::factory()->for($user)->create(['name' => 'Apple']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeInOrder(['Apple', 'Zebra']);
    }

    public function test_a_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'My Project',
            'description' => 'A test project',
        ]);

        $project = Project::first();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame($user->id, $project->user_id);
    }

    public function test_a_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
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

        $response->assertRedirect(route('projects.show', $project));
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

    public function test_a_main_plotline_is_created_automatically_with_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertSame(1, $project->plotlines()->count());
        $this->assertTrue($project->plotlines()->first()->is_main);
        $this->assertSame('Main plotline', $project->plotlines()->first()->name);
        $this->assertNotNull($project->plotlines()->first()->color);
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
        ])->assertRedirect(route('projects.show', $projectB));

        $this->assertSame(2, $projectB->plotlines()->count());
    }

    public function test_the_main_plotline_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();

        $this->actingAs($user)->delete(route('plotlines.destroy', $mainPlotline))->assertForbidden();
        $this->assertNotNull($mainPlotline->fresh());
    }

    public function test_a_regular_plotline_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create();

        $this->actingAs($user)->delete(route('plotlines.destroy', $plotline))
            ->assertRedirect(route('projects.show', $project));

        $this->assertNull($plotline->fresh());
    }

    public function test_a_user_can_create_an_event_attached_to_plotlines(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = $project->plotlines()->first();

        $response = $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'The Battle',
            'description' => 'A big fight',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
            'plotlines' => [$plotline->id],
        ]);

        $response->assertRedirect(route('projects.show', $project));
        $event = Event::first();
        $this->assertSame('The Battle', $event->title);
        $this->assertTrue($event->plotlines->contains($plotline));
    }

    public function test_a_user_cannot_create_an_event_for_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = $project->plotlines()->first();

        $this->actingAs($other)->post(route('projects.events.store', $project), [
            'title' => 'The Battle',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
            'plotlines' => [$plotline->id],
        ])->assertForbidden();
    }

    public function test_an_event_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create();
        $event->plotlines()->attach($plotline);

        $this->actingAs($user)->put(route('events.update', $event), [
            'title' => 'Updated Title',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
            'plotlines' => [$plotline->id],
        ])->assertRedirect(route('projects.show', $project));

        $this->assertSame('Updated Title', $event->fresh()->title);

        $this->actingAs($user)->delete(route('events.destroy', $event))
            ->assertRedirect(route('projects.show', $project));

        $this->assertNull($event->fresh());
    }
}
