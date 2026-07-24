<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for EventController.
 *
 * Project-creation invariants that seed the Start/End bookends live in ProjectTest;
 * here we cover the controller's own CRUD, authorization, the is_fixed deletion
 * guard, and WithinEventWindow enforcement on the store/update write paths.
 */
class EventTest extends TestCase
{
    use RefreshDatabase;

    // --- Index -------------------------------------------------------------

    public function test_a_user_can_view_the_events_index_for_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Event::factory()->for($project)->create(['title' => 'A Named Event']);

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

    // --- Create / store ----------------------------------------------------

    public function test_a_user_can_view_the_event_create_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.events.create', $project))->assertOk();
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

    public function test_creating_an_event_requires_at_least_one_plotline(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'Unlinked Event',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('plotlines');
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

    // --- Edit / update -----------------------------------------------------

    public function test_an_event_can_be_updated(): void
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
    }

    public function test_saving_the_edit_form_records_a_labeled_manual_revision_for_the_changed_description(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $plotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create(['description' => 'Old description']);
        $event->plotlines()->attach($plotline);

        $this->actingAs($user)->put(route('events.update', $event), [
            'title' => $event->title,
            'event_datetime' => $event->event_datetime->format('Y-m-d H:i:s'),
            'description' => 'New description',
            'plotlines' => [$plotline->id],
        ]);

        $revision = $event->revisions()->where('field', 'description')->latest('created_at')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionOrigin::Manual, $revision->origin);
        $this->assertNotNull($revision->label);
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

    public function test_a_user_cannot_update_an_event_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $plotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create();
        $event->plotlines()->attach($plotline);

        $this->actingAs($other)->put(route('events.update', $event), [
            'title' => 'Hijacked',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
            'plotlines' => [$plotline->id],
        ])->assertForbidden();
    }

    // --- Destroy -----------------------------------------------------------

    public function test_a_regular_event_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $event = Event::factory()->for($project)->create();

        $this->actingAs($user)->delete(route('events.destroy', $event))
            ->assertRedirect(route('projects.events.index', $project));

        $this->assertNull($event->fresh());
    }

    public function test_a_fixed_event_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->events()->where('title', 'Start')->first();

        $this->actingAs($user)->delete(route('events.destroy', $start))->assertForbidden();
        $this->assertNotNull($start->fresh());
    }

    public function test_a_user_cannot_delete_an_event_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $event = Event::factory()->for($project)->create();

        $this->actingAs($other)->delete(route('events.destroy', $event))->assertForbidden();
        $this->assertNotNull($event->fresh());
    }

    // --- WithinEventWindow bounds on the write paths -----------------------

    public function test_a_regular_event_cannot_be_created_outside_the_window(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();

        // Bring End in to 2020 so there is a ceiling to cross (set directly to bypass the form).
        $project->endEvent()->update(['event_datetime' => '2020-01-01 00:00:00']);

        $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'Too late',
            'event_datetime' => '2021-01-01 00:00:00',
            'plotlines' => [$mainPlotline->id],
        ])->assertSessionHasErrors('event_datetime');

        // Only the two bookends remain.
        $this->assertSame(2, $project->events()->count());
    }

    public function test_a_regular_event_cannot_be_updated_outside_the_window(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();
        $event = Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);
        $event->plotlines()->attach($mainPlotline);

        // Pull End in so 2021 now sits past the window ceiling.
        $project->endEvent()->update(['event_datetime' => '2020-06-01 00:00:00']);

        $this->actingAs($user)->put(route('events.update', $event), [
            'title' => $event->title,
            'event_datetime' => '2021-01-01 00:00:00',
            'plotlines' => [$mainPlotline->id],
        ])->assertSessionHasErrors('event_datetime');

        $this->assertSame('2020', $event->fresh()->event_datetime->format('Y'));
    }

    public function test_a_bookend_datetime_can_be_changed_within_the_window(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();
        $mainPlotline = $project->plotlines()->first();

        // No regular events yet, so Start may move anywhere before End.
        $this->actingAs($user)->put(route('events.update', $start), [
            'title' => $start->title,
            'event_datetime' => '1000-01-01 00:00:00',
            'plotlines' => [$mainPlotline->id],
        ])->assertRedirect(route('projects.events.index', $project));

        $this->assertSame('1000', $start->fresh()->event_datetime->format('Y'));
        // Still the earliest fixed event, so it remains the Start anchor.
        $this->assertSame($start->id, $project->startEvent()->id);
    }

    public function test_the_start_bookend_cannot_move_past_an_existing_event(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();
        $mainPlotline = $project->plotlines()->first();

        // A regular event sits inside the window; Start may not jump past it.
        Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);

        $this->actingAs($user)->put(route('events.update', $start), [
            'title' => $start->title,
            'event_datetime' => '2021-01-01 00:00:00',
            'plotlines' => [$mainPlotline->id],
        ])->assertSessionHasErrors('event_datetime');

        $this->assertSame('0001', $start->fresh()->event_datetime->format('Y'));
    }

    public function test_the_end_bookend_cannot_move_before_an_existing_event(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $end = $project->endEvent();
        $mainPlotline = $project->plotlines()->first();

        Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);

        $this->actingAs($user)->put(route('events.update', $end), [
            'title' => $end->title,
            'event_datetime' => '2019-01-01 00:00:00',
            'plotlines' => [$mainPlotline->id],
        ])->assertSessionHasErrors('event_datetime');

        $this->assertSame('3000', $end->fresh()->event_datetime->format('Y'));
    }

    public function test_the_bookend_edit_page_renders_an_editable_datetime_input(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();

        $this->actingAs($user)->get(route('events.edit', $start))
            ->assertOk()
            ->assertSee('name="event_datetime"', false)
            ->assertSee('type="datetime-local"', false);
    }

    public function test_a_fixed_event_title_can_be_edited_with_its_datetime_resubmitted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $start = $project->startEvent();
        $mainPlotline = $project->plotlines()->first();
        $originalDatetime = $start->event_datetime;

        $this->actingAs($user)->put(route('events.update', $start), [
            'title' => 'Beginning',
            'event_datetime' => $start->event_datetime->format('Y-m-d H:i:s'),
            'plotlines' => [$mainPlotline->id],
        ])->assertRedirect(route('projects.events.index', $project));

        $fresh = $start->fresh();
        $this->assertSame('Beginning', $fresh->title);
        $this->assertTrue($originalDatetime->equalTo($fresh->event_datetime));
        $this->assertSame($start->id, $project->startEvent()->id);
    }
}
