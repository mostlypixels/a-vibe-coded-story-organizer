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
use Tests\TestCase;

class CodexEntryAttributeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: CodexEntry, 3: CodexAttribute}
     */
    private function makePair(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();

        return [$user, $project, $entry, $attribute];
    }

    public function test_attach_creates_a_start_anchored_blank_row(): void
    {
        [$user, $project, $entry, $attribute] = $this->makePair();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        $this->actingAs($user)
            ->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $attribute->id])
            ->assertRedirect(route('codex.edit', $entry));

        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $start->id,
            'value' => '',
        ]);
        $this->assertSame(1, $entry->attributeValues()->where('codex_attribute_id', $attribute->id)->count());
    }

    public function test_attaching_twice_makes_no_duplicate(): void
    {
        [$user, , $entry, $attribute] = $this->makePair();

        $this->actingAs($user)->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $attribute->id]);
        $this->actingAs($user)->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $attribute->id]);

        $this->assertSame(1, $entry->attributeValues()->where('codex_attribute_id', $attribute->id)->count());
    }

    public function test_attach_of_another_projects_attribute_fails_validation(): void
    {
        // codex_attribute_id is a posted field validated by a project-scoped Rule::exists,
        // so a foreign attribute never reaches the controller's project_id guard.
        [$user, , $entry] = $this->makePair();
        $otherProject = Project::factory()->for($user)->create();
        $foreignAttribute = CodexAttribute::factory()->for($otherProject)->appliesTo(CodexEntryType::Character)->create();

        $this->actingAs($user)
            ->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $foreignAttribute->id])
            ->assertSessionHasErrors('codex_attribute_id');

        $this->assertSame(0, $entry->attributeValues()->where('codex_attribute_id', $foreignAttribute->id)->count());
    }

    public function test_attach_of_an_attribute_not_applicable_to_the_entry_type_fails_validation(): void
    {
        [$user, $project, $entry] = $this->makePair();
        $locationOnly = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Location)->create();

        $this->actingAs($user)
            ->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $locationOnly->id])
            ->assertSessionHasErrors('codex_attribute_id');

        $this->assertSame(0, $entry->attributeValues()->where('codex_attribute_id', $locationOnly->id)->count());
    }

    public function test_detach_deletes_all_of_that_pairs_rows(): void
    {
        [$user, $project, $entry, $attribute] = $this->makePair();
        $start = $project->events()->where('title', 'Start')->firstOrFail();
        $halloween = Event::factory()->for($project)->create(['event_datetime' => '2020-10-31 00:00:00']);

        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($halloween, 'green');

        $this->actingAs($user)
            ->delete(route('codex.attributes.detach', [$entry, $attribute]))
            ->assertRedirect(route('codex.edit', $entry));

        $this->assertSame(0, $entry->attributeValues()->where('codex_attribute_id', $attribute->id)->count());
    }

    public function test_detach_leaves_the_attribute_and_other_entries_values_alone(): void
    {
        [$user, $project, $entry, $attribute] = $this->makePair();
        $otherEntry = CodexEntry::factory()->for($project)->character()->create();
        $start = $project->events()->where('title', 'Start')->firstOrFail();

        (new AttributeTimeline($entry, $attribute))->upsertAt($start, 'blonde');
        (new AttributeTimeline($otherEntry, $attribute))->upsertAt($start, 'brunette');

        $this->actingAs($user)->delete(route('codex.attributes.detach', [$entry, $attribute]));

        $this->assertDatabaseHas('codex_attributes', ['id' => $attribute->id]);
        $this->assertSame(1, $otherEntry->attributeValues()->where('codex_attribute_id', $attribute->id)->count());
    }

    public function test_detach_of_another_projects_attribute_is_not_found(): void
    {
        [$user, , $entry] = $this->makePair();
        $otherProject = Project::factory()->for($user)->create();
        $foreignAttribute = CodexAttribute::factory()->for($otherProject)->appliesTo(CodexEntryType::Character)->create();

        $this->actingAs($user)
            ->delete(route('codex.attributes.detach', [$entry, $foreignAttribute]))
            ->assertNotFound();
    }

    public function test_non_owner_cannot_attach_or_detach(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();

        $this->actingAs($other)
            ->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $attribute->id])
            ->assertForbidden();

        (new AttributeTimeline($entry, $attribute))->ensureBaseline('');

        $this->actingAs($other)
            ->delete(route('codex.attributes.detach', [$entry, $attribute]))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_on_both_routes(): void
    {
        [, , $entry, $attribute] = $this->makePair();

        $this->post(route('codex.attributes.attach', $entry), ['codex_attribute_id' => $attribute->id])
            ->assertRedirect(route('login'));

        $this->delete(route('codex.attributes.detach', [$entry, $attribute]))
            ->assertRedirect(route('login'));
    }
}
