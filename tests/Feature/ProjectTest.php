<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Project resource itself.
 *
 * The plotline and event controllers have their own dedicated test files
 * (PlotlineTest, EventTest). What stays here is project-scoped: the dashboard,
 * project CRUD/authorization, and the model invariants that project creation
 * seeds — the auto-created main plotline and the Start/End bookend events — plus
 * the Project::startEvent()/endEvent() bookend helpers.
 */
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

    // --- Project-creation invariants ---------------------------------------

    public function test_a_main_plotline_is_created_automatically_with_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertSame(1, $project->plotlines()->count());
        $this->assertTrue($project->plotlines()->first()->is_main);
        $this->assertSame('Main plotline', $project->plotlines()->first()->name);
        $this->assertNotNull($project->plotlines()->first()->color);
    }

    public function test_start_and_end_events_are_created_automatically_with_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();

        $this->assertSame(2, $project->events()->count());

        $start = $project->events()->where('title', 'Start')->first();
        $end = $project->events()->where('title', 'End')->first();

        $this->assertNotNull($start);
        $this->assertNotNull($end);
        $this->assertTrue($start->is_fixed);
        $this->assertTrue($end->is_fixed);
        $this->assertSame('0001', $start->event_datetime->format('Y'));
        $this->assertSame('3000', $end->event_datetime->format('Y'));
        $this->assertTrue($start->plotlines->contains($mainPlotline));
        $this->assertTrue($end->plotlines->contains($mainPlotline));
    }

    public function test_start_and_end_event_helpers_resolve_the_bookends(): void
    {
        $project = Project::factory()->create();

        $start = $project->startEvent();
        $end = $project->endEvent();

        $this->assertTrue($start->is_fixed);
        $this->assertTrue($end->is_fixed);
        $this->assertSame('0001', $start->event_datetime->format('Y'));
        $this->assertSame('3000', $end->event_datetime->format('Y'));
        $this->assertSame('Start', $start->title);
        $this->assertSame('End', $end->title);
    }

    public function test_the_bookend_helpers_break_datetime_ties_by_id(): void
    {
        $project = Project::factory()->create();

        // Replace the auto-created bookends with two fixed events sharing one datetime,
        // so only the id tie-break can distinguish them (delete at the model level —
        // the is_fixed guard lives in the controller, not the DB).
        $project->events()->delete();

        $sharedDatetime = '1500-06-15 00:00:00';

        $lower = Event::factory()->for($project)->create([
            'event_datetime' => $sharedDatetime,
            'is_fixed' => true,
        ]);
        $higher = Event::factory()->for($project)->create([
            'event_datetime' => $sharedDatetime,
            'is_fixed' => true,
        ]);

        $this->assertLessThan($higher->id, $lower->id);

        // Both share a datetime, so only the id tie-break distinguishes them: lowest
        // id wins startEvent(), highest id wins endEvent().
        $this->assertSame($lower->id, $project->startEvent()->id);
        $this->assertSame($higher->id, $project->endEvent()->id);
    }
}
