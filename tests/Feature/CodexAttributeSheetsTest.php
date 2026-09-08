<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Services\AttributeTimeline;
use App\Services\CodexAsOfResolver;
use App\Services\CodexAttributeSheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexAttributeSheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_only_omits_an_unset_attribute_and_keeps_one_with_only_a_baseline(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $startEvent = $project->startEvent();

        $unsetAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Eye colour']);
        $setAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        (new AttributeTimeline($entry, $setAttribute))->ensureBaseline('blonde');

        $sheets = (new CodexAttributeSheets)->setOnly($entry, $startEvent);

        $this->assertNull($sheets->firstWhere('attribute.id', $unsetAttribute->id));

        $sheet = $sheets->firstWhere('attribute.id', $setAttribute->id);
        $this->assertNotNull($sheet);
        $this->assertSame('blonde', $sheet['baseline']->value);
        $this->assertTrue($sheet['periods']->isEmpty());
    }

    public function test_attached_keeps_a_blank_baseline_and_drops_an_unset_attribute(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $startEvent = $project->startEvent();

        $unsetAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Eye colour']);
        $blankAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        (new AttributeTimeline($entry, $blankAttribute))->ensureBaseline('');

        $sheets = (new CodexAttributeSheets)->attached($entry, $startEvent);

        $this->assertNull($sheets->firstWhere('attribute.id', $unsetAttribute->id));

        $sheet = $sheets->firstWhere('attribute.id', $blankAttribute->id);
        $this->assertNotNull($sheet);
        $this->assertSame('', $sheet['baseline']->value);
    }

    public function test_set_only_drops_a_pair_whose_only_row_is_blank(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $startEvent = $project->startEvent();

        $blankAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        (new AttributeTimeline($entry, $blankAttribute))->ensureBaseline('');

        $sheets = (new CodexAttributeSheets)->setOnly($entry, $startEvent);

        $this->assertNull($sheets->firstWhere('attribute.id', $blankAttribute->id));
    }

    public function test_set_only_keeps_a_pair_with_a_blank_start_but_a_filled_later_period(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $startEvent = $project->startEvent();
        $laterEvent = Event::factory()->for($project)->create(['event_datetime' => now()->addYear()]);

        $attribute = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        $timeline = new AttributeTimeline($entry, $attribute);
        $timeline->ensureBaseline('');
        $timeline->upsertAt($laterEvent, 'grey');

        $sheets = (new CodexAttributeSheets)->setOnly($entry, $startEvent);

        $sheet = $sheets->firstWhere('attribute.id', $attribute->id);
        $this->assertNotNull($sheet);
        $this->assertSame('', $sheet['baseline']->value);
    }

    public function test_unattached_for_excludes_attached_ids_and_other_entry_types(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $attached = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        (new AttributeTimeline($entry, $attached))->ensureBaseline('blonde');

        $unattached = CodexAttribute::factory()->for($project)->create(['name' => 'Eye colour']);
        $otherType = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Location)->create(['name' => 'Terrain']);

        $entry->load('attributeValues.startEvent');

        $options = (new CodexAttributeSheets)->unattachedFor($entry);

        $this->assertNull($options->firstWhere('id', $attached->id));
        $this->assertNull($options->firstWhere('id', $otherType->id));
        $this->assertNotNull($options->firstWhere('id', $unattached->id));
    }

    public function test_codex_as_of_resolver_agrees_with_set_only_for_an_all_blank_pair(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $startEvent = $project->startEvent();

        $attribute = CodexAttribute::factory()->for($project)->create(['name' => 'Hair colour']);
        (new AttributeTimeline($entry, $attribute))->ensureBaseline('');

        $entry->load('attributeValues.startEvent');
        $sheets = (new CodexAttributeSheets)->setOnly($entry, $startEvent);
        $this->assertTrue($sheets->isEmpty());

        $groups = (new CodexAsOfResolver)->resolve($project, $startEvent);
        $characterGroup = $groups->firstWhere('type', CodexEntryType::Character);

        // Both agree the entry has nothing to show: the resolver drops it entirely.
        $this->assertNull($characterGroup);
    }
}
