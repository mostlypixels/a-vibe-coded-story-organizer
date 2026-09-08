<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\DateFormat;
use App\Support\LocaleChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for QuickEventController — the AJAX endpoint that backs the
 * inline "new event" pickers on the scene and codex forms.
 */
class QuickEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_create_a_quick_event(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->where('is_main', true)->sole();

        $response = $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'The Second Curse',
            'event_datetime' => '1215-04-02 00:00:00',
        ]);

        $response->assertCreated();

        $event = Event::where('title', 'The Second Curse')->sole();
        $this->assertTrue($event->plotlines->contains($mainPlotline));

        $response->assertJson([
            'id' => $event->id,
            'title' => 'The Second Curse',
            'datetime' => '1215-04-02T00:00',
        ]);
        $response->assertJsonStructure(['id', 'title', 'datetime', 'label']);
    }

    public function test_the_label_matches_what_the_picker_renders(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'The Battle',
            'event_datetime' => '1247-03-15 14:30:00',
        ]);

        $event = Event::where('title', 'The Battle')->sole();
        $expectedLabel = $event->title.' — '.DateFormat::date($event->event_datetime, LocaleChoice::resolve('fr'));

        $response->assertJson(['label' => $expectedLabel]);
    }

    public function test_a_missing_title_is_rejected_and_creates_nothing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');

        // Only the two bookends remain.
        $this->assertSame(2, $project->events()->count());
    }

    public function test_a_datetime_outside_the_window_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $project->endEvent()->update(['event_datetime' => '2020-01-01 00:00:00']);

        $response = $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'Too late',
            'event_datetime' => '2021-01-01 00:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('event_datetime');

        $this->assertSame(2, $project->events()->count());
    }

    public function test_a_non_owner_cannot_create_a_quick_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'The Battle',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->post(route('projects.events.quick-store', $project), [
            'title' => 'The Battle',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('login'));
    }

    public function test_the_scene_the_request_came_from_is_unchanged(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'The Battle',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $scene->refresh();
        $this->assertNull($scene->event_id);
    }

    public function test_the_route_resolves_before_the_events_resource(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // An event with id 1 exists in this project (the Start bookend), so a
        // resource-ordering mistake would bind "quick" as {event} and 404/fail here.
        $this->assertSame(1, $project->startEvent()->id);

        $this->actingAs($user)->postJson(route('projects.events.quick-store', $project), [
            'title' => 'The Battle',
            'event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertCreated();
    }
}
