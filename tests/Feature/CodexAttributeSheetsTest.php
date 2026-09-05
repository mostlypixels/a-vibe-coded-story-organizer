<?php

namespace Tests\Feature;

use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\AttributeTimeline;
use App\Services\CodexAttributeSheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexAttributeSheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_entry_includes_an_attribute_the_entry_has_no_value_for(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $unsetAttribute = CodexAttribute::factory()->for($project)->create(['name' => 'Eye colour']);

        $sheets = (new CodexAttributeSheets)->forEntry($entry, $project->startEvent());

        $sheet = $sheets->firstWhere('attribute.id', $unsetAttribute->id);
        $this->assertNotNull($sheet);
        $this->assertNull($sheet['baseline']);
        $this->assertTrue($sheet['periods']->isEmpty());
    }

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
}
