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

        $response->assertRedirect(route('projects.plotlines.index', $project));
        $this->assertSame(2, $project->plotlines()->count());
    }

    public function test_a_user_can_view_the_plotlines_index_for_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = Plotline::factory()->for($project)->create(['name' => 'A Named Plotline']);

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
        ])->assertRedirect(route('projects.plotlines.index', $projectB));

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
            ->assertRedirect(route('projects.plotlines.index', $project));

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

        $response->assertRedirect(route('projects.events.index', $project));
        $event = Event::where('title', 'The Battle')->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->plotlines->contains($plotline));
    }

    public function test_a_user_can_view_the_events_index_for_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $event = Event::factory()->for($project)->create(['title' => 'A Named Event']);

        $this->actingAs($user)->get(route('projects.events.index', $project))
            ->assertOk()
            ->assertSee('A Named Event');
    }

    public function test_the_events_index_can_be_sorted_by_title(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Event::factory()->for($project)->create(['title' => 'Zebra Event']);
        Event::factory()->for($project)->create(['title' => 'Apple Event']);

        $this->actingAs($user)->get(route('projects.events.index', ['project' => $project, 'sort' => 'title', 'direction' => 'asc']))
            ->assertSeeInOrder(['Apple Event', 'Zebra Event']);
    }

    public function test_the_events_index_can_be_filtered_by_title_and_plotline(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotlineA = Plotline::factory()->for($project)->create();
        $plotlineB = Plotline::factory()->for($project)->create();

        $eventA = Event::factory()->for($project)->create(['title' => 'The Battle']);
        $eventA->plotlines()->attach($plotlineA);

        $eventB = Event::factory()->for($project)->create(['title' => 'The Wedding']);
        $eventB->plotlines()->attach($plotlineB);

        $bySearch = $this->actingAs($user)->get(route('projects.events.index', ['project' => $project, 'search' => 'Battle']));
        $bySearch->assertSee('The Battle');
        $bySearch->assertDontSee('The Wedding');

        $byPlotline = $this->actingAs($user)->get(route('projects.events.index', ['project' => $project, 'plotline' => $plotlineB->id]));
        $byPlotline->assertSee('The Wedding');
        $byPlotline->assertDontSee('The Battle');
    }

    public function test_a_user_cannot_view_the_events_index_for_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('projects.events.index', $project))->assertForbidden();
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
        ])->assertRedirect(route('projects.events.index', $project));

        $this->assertSame('Updated Title', $event->fresh()->title);

        $this->actingAs($user)->delete(route('events.destroy', $event))
            ->assertRedirect(route('projects.events.index', $project));

        $this->assertNull($event->fresh());
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
        $this->assertSame('0000', $start->event_datetime->format('Y'));
        $this->assertSame('3000', $end->event_datetime->format('Y'));
        $this->assertTrue($start->plotlines->contains($mainPlotline));
        $this->assertTrue($end->plotlines->contains($mainPlotline));
    }

    public function test_a_fixed_event_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->events()->where('title', 'Start')->first();

        $this->actingAs($user)->delete(route('events.destroy', $start))->assertForbidden();
        $this->assertNotNull($start->fresh());
    }

    public function test_start_and_end_event_helpers_resolve_the_bookends(): void
    {
        $project = Project::factory()->create();

        $start = $project->startEvent();
        $end = $project->endEvent();

        $this->assertTrue($start->is_fixed);
        $this->assertTrue($end->is_fixed);
        $this->assertSame('0000', $start->event_datetime->format('Y'));
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

    public function test_fixed_event_datetime_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();
        $mainPlotline = $project->plotlines()->first();
        $originalDatetime = $start->event_datetime;

        $this->actingAs($user)->put(route('events.update', $start), [
            'title' => $start->title,
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
            'plotlines' => [$mainPlotline->id],
        ])->assertSessionHasErrors('event_datetime');

        $this->assertTrue($originalDatetime->equalTo($start->fresh()->event_datetime));

        // Regression: the frozen datetime keeps Start resolving to the same event.
        $this->assertSame($start->id, $project->startEvent()->id);
    }

    public function test_a_fixed_event_can_be_edited_without_touching_its_datetime(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();
        $mainPlotline = $project->plotlines()->first();
        $originalDatetime = $start->event_datetime;

        $this->actingAs($user)->put(route('events.update', $start), [
            'title' => 'Beginning',
            'plotlines' => [$mainPlotline->id],
        ])->assertRedirect(route('projects.events.index', $project));

        $fresh = $start->fresh();
        $this->assertSame('Beginning', $fresh->title);
        $this->assertTrue($originalDatetime->equalTo($fresh->event_datetime));
        $this->assertSame($start->id, $project->startEvent()->id);
    }

    public function test_a_non_fixed_event_datetime_can_still_be_changed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create();
        $event->plotlines()->attach($plotline);

        $newDatetime = now()->addWeek()->startOfMinute();

        $this->actingAs($user)->put(route('events.update', $event), [
            'title' => 'Updated Title',
            'event_datetime' => $newDatetime->format('Y-m-d H:i:s'),
            'plotlines' => [$plotline->id],
        ])->assertRedirect(route('projects.events.index', $project));

        $this->assertTrue($newDatetime->equalTo($event->fresh()->event_datetime));
    }
}
