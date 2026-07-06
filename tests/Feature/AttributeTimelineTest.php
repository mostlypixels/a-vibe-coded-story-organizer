<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Services\AttributeTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class AttributeTimelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a project (with its automatic Start/End events) plus a character entry and an
     * attribute that applies to it, ready for timeline assertions.
     *
     * @return array{0: Project, 1: CodexEntry, 2: CodexAttribute, 3: Event}
     */
    private function makePair(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        return [$project, $entry, $attribute, $start];
    }

    public function test_the_first_value_is_anchored_at_the_projects_start_event(): void
    {
        [, $entry, $attribute, $start] = $this->makePair();

        $baseline = (new AttributeTimeline($entry, $attribute))->ensureBaseline('blonde');

        $this->assertSame($start->id, $baseline->start_event_id);
        $this->assertSame('blonde', $baseline->value);
    }

    public function test_ensure_baseline_is_idempotent(): void
    {
        [, $entry, $attribute] = $this->makePair();
        $timeline = new AttributeTimeline($entry, $attribute);

        $first = $timeline->ensureBaseline('blonde');
        $second = $timeline->ensureBaseline('green');

        // Same row returned, original value kept, and exactly one row for the pair.
        $this->assertSame($first->id, $second->id);
        $this->assertSame('blonde', $second->value);
        $this->assertSame(1, $entry->attributeValues()->count());
    }

    public function test_it_resolves_the_value_in_effect_at_a_datetime(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);
        $backToClass = Event::factory()->for($project)->create(['event_datetime' => '2020-11-15 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($halloween, 'green');
        $timeline->upsertAt($backToClass, 'black');

        $this->assertSame('blonde', $timeline->valueAt(Carbon::parse('2020-06-01'))->value);
        $this->assertSame('green', $timeline->valueAt(Carbon::parse('2020-10-31 00:00:00'))->value);
        $this->assertSame('black', $timeline->valueAt(Carbon::parse('2022-01-01'))->value);
    }

    public function test_resolution_is_total_for_any_datetime_after_start(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($halloween, 'green');

        foreach (['0001-01-01 00:00:00', '2020-10-31 00:00:00', '2999-12-31 23:59:59'] as $moment) {
            $this->assertNotNull(
                $timeline->valueAt(Carbon::parse($moment)),
                "valueAt({$moment}) must never be null when a baseline exists"
            );
        }
    }

    public function test_upserting_at_an_existing_anchor_updates_the_row(): void
    {
        [, $entry, $attribute, $start] = $this->makePair();
        $timeline = new AttributeTimeline($entry, $attribute);

        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($start, 'green');

        // Still a single row for that anchor, now carrying the new value.
        $this->assertSame(1, $entry->attributeValues()->where('start_event_id', $start->id)->count());
        $this->assertSame('green', $timeline->valueAt($start)->value);
    }

    public function test_anchors_sharing_a_datetime_resolve_deterministically(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();

        // Two anchors at the exact same datetime; the first created has the lower id.
        $lowerIdEvent = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);
        $higherIdEvent = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'base');
        $timeline->upsertAt($lowerIdEvent, 'lower-id');
        $timeline->upsertAt($higherIdEvent, 'higher-id');

        // A bare datetime resolves to the last anchor in canonical (datetime, id) order.
        $this->assertSame('higher-id', $timeline->valueAt(Carbon::parse('2020-10-31 00:00:00'))->value);

        // But passing an anchor event returns that anchor's own value (identity wins).
        $this->assertSame('lower-id', $timeline->valueAt($lowerIdEvent)->value);
        $this->assertSame('higher-id', $timeline->valueAt($higherIdEvent)->value);
    }

    public function test_deleting_a_middle_anchor_event_keeps_the_timeline_gap_free(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $middle = Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);
        $late = Event::factory()->for($project)->create(['event_datetime' => '2020-12-01 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'base');
        $timeline->upsertAt($middle, 'mid');
        $timeline->upsertAt($late, 'late');

        $this->assertSame('mid', $timeline->valueAt(Carbon::parse('2020-06-01'))->value);

        // Deleting the anchoring event cascades to its value row (cascadeOnDelete)...
        $middle->delete();
        $this->assertDatabaseMissing('codex_attribute_values', ['start_event_id' => $middle->id]);

        // ...and the previous period extends to fill the gap rather than leaving a hole.
        $this->assertSame('base', (new AttributeTimeline($entry, $attribute))->valueAt(Carbon::parse('2020-06-01'))->value);
    }

    public function test_remove_at_refuses_to_delete_the_start_baseline_while_other_values_exist(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $later = Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'base');
        $timeline->upsertAt($later, 'later');

        try {
            $timeline->removeAt($start);
            $this->fail('Removing the Start baseline while other values exist should throw.');
        } catch (RuntimeException) {
            // Expected: the baseline row must survive.
        }

        $this->assertSame(1, $entry->attributeValues()->where('start_event_id', $start->id)->count());
    }

    public function test_remove_at_deletes_the_start_baseline_when_it_is_the_only_value(): void
    {
        [, $entry, $attribute, $start] = $this->makePair();
        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'base');

        $timeline->removeAt($start);

        $this->assertSame(0, $entry->attributeValues()->count());
    }

    public function test_attribute_value_at_returns_null_for_an_unassigned_scene(): void
    {
        [, $entry, $attribute] = $this->makePair();
        (new AttributeTimeline($entry, $attribute))->ensureBaseline('blonde');

        // A scene with no "happens during" event resolves to undetermined, not a crash.
        $this->assertNull($entry->attributeValueAt($attribute, null));
    }

    public function test_attribute_value_at_resolves_through_an_event(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $backToClass = Event::factory()->for($project)->create(['event_datetime' => '2020-11-15 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($backToClass, 'black');

        $this->assertSame('black', $entry->attributeValueAt($attribute, $backToClass));
    }

    // --- HTTP: timeline period endpoints (store as upsert / destroy) -----------------------

    public function test_store_creates_a_period(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        $this->actingAs($user)
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => $halloween->id,
                'value' => 'green',
            ])
            ->assertRedirect(route('codex.edit', $entry));

        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $halloween->id,
            'value' => 'green',
        ]);

        // Gap-free invariant: storing a mid-timeline period on a never-valued pair must also
        // create the '' Start baseline, so the timeline has no hole before the new anchor.
        $start = $project->startEvent();
        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $start->id,
            'value' => '',
        ]);
    }

    public function test_upsert_at_a_mid_timeline_event_seeds_a_start_baseline(): void
    {
        [$project, $entry, $attribute, $start] = $this->makePair();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        (new AttributeTimeline($entry, $attribute))->upsertAt($halloween, 'green');

        // A moment before the mid-timeline anchor resolves to the '' baseline, not a hole.
        $beforeHalloween = Carbon::parse('2020-10-30 00:00:00');
        $resolved = (new AttributeTimeline($entry, $attribute))->valueAt($beforeHalloween);
        $this->assertNotNull($resolved);
        $this->assertSame($start->id, $resolved->start_event_id);
        $this->assertSame('', $resolved->value);
    }

    public function test_upsert_at_the_start_event_does_not_double_write(): void
    {
        [, $entry, $attribute, $start] = $this->makePair();

        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');

        // The anchor is Start, so upsert is itself the baseline write: exactly one row, no
        // stray '' baseline pinned first.
        $this->assertSame(1, $entry->attributeValues()->count());
        $this->assertSame('blonde', $entry->attributeValues()->sole()->value);
    }

    public function test_store_at_an_existing_anchor_updates_in_place(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        $this->actingAs($user)->post(route('codex.attribute-values.store', [$entry, $attribute]), [
            'start_event_id' => $start->id,
            'value' => 'blonde',
        ]);

        $response = $this->actingAs($user)->post(route('codex.attribute-values.store', [$entry, $attribute]), [
            'start_event_id' => $start->id,
            'value' => 'green',
        ]);

        // Upsert: no validation error, still one row for that anchor, now carrying the new value.
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $entry->attributeValues()->where('start_event_id', $start->id)->count());
        $this->assertSame('green', $entry->attributeValues()->where('start_event_id', $start->id)->value('value'));
    }

    public function test_store_allows_an_empty_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        // '' is a first-class "recorded as blank" value (Q2): the Start baseline persists it
        // and the submit reports no errors (would fail under the old 'required' rule).
        $this->actingAs($user)
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => $start->id,
                'value' => '',
            ])
            ->assertRedirect(route('codex.edit', $entry))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $start->id,
            'value' => '',
        ]);
    }

    public function test_store_can_clear_a_value_back_to_empty(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        (new AttributeTimeline($entry, $attribute))->upsertAt($halloween, 'green');

        // Re-posting '' at the same anchor clears it (upsert), not blocked by validation.
        $this->actingAs($user)
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => $halloween->id,
                'value' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '',
            $entry->attributeValues()->where('start_event_id', $halloween->id)->value('value')
        );
    }

    public function test_store_without_an_event_shows_a_validation_error(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();

        // Add-period submit with no anchor chosen fails on start_event_id...
        $this->actingAs($user)
            ->from(route('codex.edit', $entry))
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => '',
                'value' => 'green',
            ])
            ->assertRedirect(route('codex.edit', $entry))
            ->assertSessionHasErrors('start_event_id');

        // ...and the message renders on the edit page (the partial now echoes it).
        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('The start event id field is required.');
    }

    public function test_store_rejects_a_cross_project_anchor_event(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();

        $otherProject = Project::factory()->for($user)->create();
        $foreignEvent = Event::factory()->for($otherProject)->create();

        $this->actingAs($user)
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => $foreignEvent->id,
                'value' => 'green',
            ])
            ->assertSessionHasErrors('start_event_id');

        $this->assertSame(0, $entry->attributeValues()->count());
    }

    public function test_destroy_removes_a_period_and_resolution_falls_back_to_the_previous_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($halloween, 'green');

        $period = $entry->attributeValues()->where('start_event_id', $halloween->id)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('codex.attribute-values.destroy', $period))
            ->assertRedirect(route('codex.edit', $entry));

        $this->assertDatabaseMissing('codex_attribute_values', ['id' => $period->id]);

        // The previous period extends to fill the gap rather than leaving a hole.
        $this->assertSame(
            'blonde',
            (new AttributeTimeline($entry, $attribute))->valueAt(Carbon::parse('2020-10-31 00:00:00'))->value
        );
    }

    public function test_destroy_refuses_to_remove_the_start_baseline_while_others_exist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        $later = Event::factory()->for($project)->create(['event_datetime' => '2020-01-01 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($later, 'green');

        $baseline = $entry->attributeValues()->where('start_event_id', $start->id)->firstOrFail();

        // The Blade hides Remove on the baseline, so this is a hand-crafted request; the
        // honest response is a 403 (matching the is_main / is_fixed guards), not a soft error.
        $this->actingAs($user)
            ->delete(route('codex.attribute-values.destroy', $baseline))
            ->assertStatus(403);

        // The baseline row survives so the timeline stays gap-free at the start.
        $this->assertDatabaseHas('codex_attribute_values', ['id' => $baseline->id]);
    }

    public function test_destroy_removes_the_start_baseline_when_it_is_the_only_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');
        $baseline = $entry->attributeValues()->where('start_event_id', $start->id)->firstOrFail();

        // Sole value: removing the baseline leaves the pair unvalued rather than a hole.
        $this->actingAs($user)
            ->delete(route('codex.attribute-values.destroy', $baseline))
            ->assertRedirect(route('codex.edit', $entry));

        $this->assertDatabaseMissing('codex_attribute_values', ['id' => $baseline->id]);
    }

    public function test_non_owner_cannot_store_or_destroy_periods(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        $value = (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');

        $this->actingAs($other)
            ->post(route('codex.attribute-values.store', [$entry, $attribute]), [
                'start_event_id' => $start->id,
                'value' => 'green',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('codex.attribute-values.destroy', $value))
            ->assertForbidden();
    }

    // --- HTTP: attribute_baselines on entry create ----------------------------------------

    public function test_attribute_baselines_seed_start_values_on_create(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $filled = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);
        $blank = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Height']);

        $this->actingAs($user)
            ->post(route('projects.codex.store', [$project, 'characters']), [
                'name' => 'Melusine',
                'attribute_baselines' => [$filled->id => 'blonde'],
            ])
            ->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        // The submitted attribute is seeded with its value...
        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $filled->id,
            'start_event_id' => $start->id,
            'value' => 'blonde',
        ]);

        // ...and applicable attributes not submitted still get an empty Start baseline.
        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $blank->id,
            'start_event_id' => $start->id,
            'value' => '',
        ]);
    }

    public function test_attribute_baselines_rejects_a_foreign_project_attribute(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $otherProject = Project::factory()->for($user)->create();
        $foreignAttribute = CodexAttribute::factory()->for($otherProject)->appliesTo(CodexEntryType::Character)->create();

        $this->actingAs($user)
            ->post(route('projects.codex.store', [$project, 'characters']), [
                'name' => 'Melusine',
                'attribute_baselines' => [$foreignAttribute->id => 'blonde'],
            ])
            ->assertSessionHasErrors("attribute_baselines.{$foreignAttribute->id}");

        $this->assertDatabaseMissing('codex_entries', ['name' => 'Melusine']);
    }

    public function test_attribute_baselines_rejects_an_attribute_not_applicable_to_the_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $locationOnly = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Location)->create();

        $this->actingAs($user)
            ->post(route('projects.codex.store', [$project, 'characters']), [
                'name' => 'Melusine',
                'attribute_baselines' => [$locationOnly->id => 'blonde'],
            ])
            ->assertSessionHasErrors("attribute_baselines.{$locationOnly->id}");
    }

    public function test_edit_page_renders_the_timeline_editor(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('Attribute timeline')
            ->assertSee('Hair color')
            ->assertSee('blonde');
    }
}
