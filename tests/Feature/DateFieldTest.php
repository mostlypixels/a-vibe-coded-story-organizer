<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `<x-date-field>` picker on the event forms.
 *
 * > [!WARNING]
 * > The picker must post exactly one hidden input under the original field
 * > name, carrying `Y-m-d\TH:i`. A second input with that name, or a renamed
 * > one, silently drops or doubles the value with no test failure elsewhere.
 */
class DateFieldTest extends TestCase
{
    use RefreshDatabase;

    private function hiddenDatetimeInputs(string $html): int
    {
        return preg_match_all('/<input[^>]*type="hidden"[^>]*name="event_datetime"/', $html);
    }

    public function test_the_create_form_emits_one_hidden_event_datetime_input(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.events.create', $project))->assertOk();

        $this->assertSame(1, $this->hiddenDatetimeInputs($response->getContent()));
        $this->assertStringNotContainsString('type="datetime-local"', $response->getContent());
    }

    public function test_the_edit_form_emits_one_hidden_event_datetime_input_holding_the_stored_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $event = Event::factory()->for($project)->create(['event_datetime' => '1247-03-15 14:30:00']);

        $response = $this->actingAs($user)->get(route('events.edit', $event))->assertOk();

        $this->assertSame(1, $this->hiddenDatetimeInputs($response->getContent()));
        $this->assertStringContainsString('value="1247-03-15T14:30"', $response->getContent());
    }

    public function test_the_picker_renders_month_names_in_the_users_locale(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.events.create', $project))
            ->assertOk()
            ->assertSee('janvier');
    }

    public function test_the_picker_value_saves_exactly_as_the_native_input_did(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'The Battle',
            'plotlines' => [$project->plotlines()->value('id')],
            'event_datetime' => now()->addWeek()->format('Y-m-d').'T14:30',
        ])->assertSessionHasNoErrors();

        $event = Event::where('title', 'The Battle')->sole();

        $this->assertSame(now()->addWeek()->format('Y-m-d').' 14:30:00', $event->event_datetime->format('Y-m-d H:i:s'));
    }

    public function test_a_midnight_picker_value_saves_as_midnight(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'Dawn',
            'plotlines' => [$project->plotlines()->value('id')],
            'event_datetime' => now()->addWeek()->format('Y-m-d').'T00:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            now()->addWeek()->format('Y-m-d').' 00:00:00',
            Event::where('title', 'Dawn')->sole()->event_datetime->format('Y-m-d H:i:s'),
        );
    }

    public function test_a_date_outside_the_event_window_is_still_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $project->endEvent()->update(['event_datetime' => '2020-01-01 00:00:00']);

        $this->actingAs($user)->post(route('projects.events.store', $project), [
            'title' => 'Too Late',
            'event_datetime' => '2021-01-01T00:00',
        ])->assertSessionHasErrors('event_datetime');
    }

    public function test_a_non_owner_cannot_open_the_event_form(): void
    {
        $stranger = User::factory()->create();
        $project = Project::factory()->for(User::factory())->create();

        $this->actingAs($stranger)->get(route('projects.events.create', $project))->assertForbidden();
    }
}
