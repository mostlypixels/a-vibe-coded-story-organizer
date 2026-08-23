<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\AttributeTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The read-only "as of" panels on the scene and event edit pages. These exercise the payoff
 * view: opening a scene (via its "happens during" event) or an event resolves every codex
 * entry's attribute values at that moment through CodexEntry::attributeValueAt.
 */
class CodexAsOfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A project with a Melusine character whose "Hair color" runs blonde from Start and turns
     * black from a "Back to class" event, plus a scene anchored to that event.
     *
     * @return array{0: User, 1: Project, 2: Event, 3: Scene}
     */
    private function makeScenario(): array
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);

        $start = $project->events()->where('title', 'Start')->firstOrFail();
        $backToClass = Event::factory()->for($project)->create(['title' => 'Back to class', 'event_datetime' => '2020-11-15 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($backToClass, 'black');

        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $backToClass->id]);

        return [$user, $project, $backToClass, $scene];
    }

    public function test_scene_panel_resolves_the_value_as_of_its_event(): void
    {
        [$user, , , $scene] = $this->makeScenario();

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Codex as of this scene')
            ->assertSee('Melusine')
            ->assertSee('Hair color')
            ->assertSee('black');
    }

    public function test_scene_panel_shows_the_undetermined_state_when_unassigned(): void
    {
        [$user, $project] = $this->makeScenario();
        $book = $project->books()->first();

        // A scene with no "happens during" event resolves to undetermined, not a crash.
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $unassigned = Scene::factory()->for($chapter)->create(['event_id' => null]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $unassigned))
            ->assertOk()
            ->assertSee('Codex as of this scene')
            ->assertSee('Assign an event to this scene to see codex values.')
            // No moment to resolve at → no attribute rows render at all (not even the labels).
            ->assertDontSee('Hair color');
    }

    public function test_event_panel_prefers_the_events_own_anchor_over_a_same_datetime_sibling(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        // Two anchors sharing the exact same datetime; the event being viewed is the lower-id one.
        $viewedEvent = Event::factory()->for($project)->create(['title' => 'Masquerade', 'event_datetime' => '2020-10-31 00:00:00']);
        $siblingEvent = Event::factory()->for($project)->create(['title' => 'Gala', 'event_datetime' => '2020-10-31 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($viewedEvent, 'green');
        // "crimson" (not "red") so the assertion can't collide with Tailwind's red-* classes.
        $timeline->upsertAt($siblingEvent, 'crimson');

        // Opening the viewed event resolves to its own anchored value (identity wins over the
        // sibling that shares its datetime but has the higher id).
        $this->actingAs($user)
            ->get(route('events.edit', $viewedEvent))
            ->assertOk()
            ->assertSee('Values as of this event')
            ->assertSee('green')
            ->assertDontSee('crimson');
    }

    public function test_scene_panel_shows_age_for_an_entity_alive_at_the_moment(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $inception = Event::factory()->for($project)->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melusine',
            'inception_event_id' => $inception->id,
        ]);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');

        $atMoment = Event::factory()->for($project)->create(['event_datetime' => '2000-01-01']);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $atMoment->id]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Melusine')
            ->assertSee('Age 20');
    }

    public function test_scene_panel_hides_an_entity_before_its_inception(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $inception = Event::factory()->for($project)->create(['event_datetime' => '1980-01-01']);
        CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melusine',
            'inception_event_id' => $inception->id,
        ]);

        $before = Event::factory()->for($project)->create(['event_datetime' => '1970-01-01']);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $before->id]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertDontSee('Melusine');
    }

    public function test_scene_panel_hides_an_entity_after_its_termination(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $inception = Event::factory()->for($project)->create(['event_datetime' => '1980-01-01']);
        $termination = Event::factory()->for($project)->create(['event_datetime' => '2000-01-01']);
        CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melusine',
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);

        $after = Event::factory()->for($project)->create(['event_datetime' => '2010-01-01']);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $after->id]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertDontSee('Melusine');
    }

    public function test_scene_panel_shows_an_entity_with_an_age_and_no_attribute_values(): void
    {
        // Guards the resolver keep-if-age change: before the fix, an entry with no
        // attribute values as of the moment was dropped even when it has an age to show.
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $inception = Event::factory()->for($project)->create(['event_datetime' => '1980-01-01']);
        CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melusine',
            'inception_event_id' => $inception->id,
        ]);

        $atMoment = Event::factory()->for($project)->create(['event_datetime' => '2000-01-01']);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $atMoment->id]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Melusine')
            ->assertSee('Age 20');
    }

    public function test_scene_panel_shows_an_inverted_lifespan_entity_with_no_age(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $inception = Event::factory()->for($project)->create(['event_datetime' => '2000-01-01']);
        $termination = Event::factory()->for($project)->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melusine',
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');

        $anyMoment = Event::factory()->for($project)->create(['event_datetime' => '1970-01-01']);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $anyMoment->id]);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Melusine')
            ->assertDontSee('Age ');
    }

    public function test_non_owner_cannot_open_the_scene_edit_page(): void
    {
        [, , , $scene] = $this->makeScenario();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('scenes.edit', $scene))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_open_the_event_edit_page(): void
    {
        [, , $event] = $this->makeScenario();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('events.edit', $event))
            ->assertForbidden();
    }
}
