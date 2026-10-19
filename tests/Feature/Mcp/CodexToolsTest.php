<?php

namespace Tests\Feature\Mcp;

use App\Enums\CodexEntryType;
use App\Enums\RevisionOrigin;
use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\CreateCodexEntryTool;
use App\Mcp\Tools\SetCodexAttributeTool;
use App\Mcp\Tools\UpdateCodexEntryTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Services\AttributeTimeline;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexToolsTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project, string $contents): Scene
    {
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();

        return Scene::factory()->for($chapter)->create(['contents' => $contents]);
    }

    public function test_create_links_scenes_that_name_the_entry(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project, '<p>Mel walked into the room.</p>');

        AvcsoServer::tool(CreateCodexEntryTool::class, [
            'project' => $project->id,
            'type' => 'character',
            'name' => 'Melchior',
            'description' => '<p>A king.</p>',
            'aliases' => ['Mel'],
        ])->assertOk()->assertSee(['Created Character #', '"Melchior"', 'Scenes that name it: 1']);

        $entry = CodexEntry::query()->where('name', 'Melchior')->firstOrFail();
        $this->assertSame(CodexEntryType::Character, $entry->type);
        $this->assertSame('<p>A king.</p>', $entry->description);
        $this->assertSame(['Mel'], $entry->aliases()->pluck('alias')->all());
        $this->assertDatabaseHas('scene_codex_entry', ['scene_id' => $scene->id, 'codex_entry_id' => $entry->id]);
    }

    public function test_create_rejects_an_unknown_type_and_a_missing_project(): void
    {
        $project = Project::factory()->create();

        AvcsoServer::tool(CreateCodexEntryTool::class, ['project' => $project->id, 'type' => 'dragon', 'name' => 'Smaug'])
            ->assertHasErrors(['type']);
        AvcsoServer::tool(CreateCodexEntryTool::class, ['project' => 999, 'type' => 'character', 'name' => 'Smaug'])
            ->assertHasErrors(['No project with ID #999 found.']);

        $this->assertSame(0, CodexEntry::query()->count());
    }

    public function test_a_new_alias_resyncs_references(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project, '<p>Then Mel arrived.</p>');
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);

        AvcsoServer::tool(UpdateCodexEntryTool::class, ['entry' => $entry->id, 'aliases' => ['Mel']])
            ->assertOk()->assertSee('aliases');

        $this->assertDatabaseHas('scene_codex_entry', ['scene_id' => $scene->id, 'codex_entry_id' => $entry->id]);
    }

    public function test_an_unchanged_alias_set_does_not_rescan(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);
        $entry->aliases()->create(['alias' => 'Mel']);

        $this->mock(SceneReferenceMatcher::class)->shouldReceive('syncProject')->never();

        AvcsoServer::tool(UpdateCodexEntryTool::class, ['entry' => $entry->id, 'aliases' => ['Mel'], 'description' => '<p>New.</p>'])
            ->assertOk();

        $this->assertSame('<p>New.</p>', $entry->fresh()->description);
    }

    public function test_update_keeps_every_field_it_does_not_send(): void
    {
        $project = Project::factory()->create();
        $born = Event::factory()->for($project)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create([
            'name' => 'Melchior',
            'description' => '<p>Old.</p>',
            'inception_event_id' => $born->id,
        ]);
        $entry->aliases()->create(['alias' => 'Mel']);
        $entry->tags()->attach(Tag::query()->create(['project_id' => $project->id, 'name' => 'King']));

        AvcsoServer::tool(UpdateCodexEntryTool::class, ['entry' => $entry->id, 'name' => 'Balthazar'])->assertOk();

        $entry->refresh();
        $this->assertSame('Balthazar', $entry->name);
        $this->assertSame(CodexEntryType::Character, $entry->type);
        $this->assertSame('<p>Old.</p>', $entry->description);
        $this->assertSame($born->id, $entry->inception_event_id);
        $this->assertSame(['Mel'], $entry->aliases()->pluck('alias')->all());
        $this->assertSame(['King'], $entry->tags()->pluck('name')->all());
    }

    public function test_update_with_no_field_fails(): void
    {
        $entry = CodexEntry::factory()->for(Project::factory())->character()->create();

        AvcsoServer::tool(UpdateCodexEntryTool::class, ['entry' => $entry->id, 'type' => 'location'])
            ->assertHasErrors(['Send at least one field to change']);

        $this->assertSame(CodexEntryType::Character, $entry->fresh()->type);
    }

    public function test_a_description_change_records_one_claude_revision(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['description' => '<p>Old.</p>']);

        AvcsoServer::tool(UpdateCodexEntryTool::class, ['entry' => $entry->id, 'description' => '<p>New.</p>'])->assertOk();

        $manual = $entry->revisions()->where('field', 'description')->where('origin', RevisionOrigin::Manual)->get();
        $this->assertCount(1, $manual);
        $this->assertSame('Claude', $manual->first()->label);
        $this->assertSame($project->user_id, $manual->first()->user_id);
        $this->assertSame('<p>New.</p>', $manual->first()->value);
    }

    public function test_set_attribute_at_a_later_event_adds_a_value_and_keeps_the_start_baseline(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Goal']);
        $start = $project->startEvent();
        $later = Event::factory()->for($project)->create(['title' => 'The Fall', 'event_datetime' => $start->event_datetime->copy()->addDay()]);

        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => 'goal', 'value' => 'Find the star'])
            ->assertOk()->assertSee(['Set Goal', "event #{$start->id}"]);
        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => 'Goal', 'value' => 'Go home', 'start_event' => $later->id])
            ->assertOk()->assertSee("event #{$later->id} The Fall");

        $timeline = new AttributeTimeline($entry->fresh(), $attribute);
        $this->assertSame(['Find the star', 'Go home'], $timeline->periods()->pluck('value')->all());
        $this->assertSame($start->id, $timeline->periods()->first()->start_event_id);
        $this->assertSame('Go home', $timeline->valueAt($later)->value);
    }

    public function test_set_attribute_at_a_later_event_first_gives_a_blank_start_baseline(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Goal']);
        $later = Event::factory()->for($project)->create(['event_datetime' => $project->startEvent()->event_datetime->copy()->addDay()]);

        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => (string) $attribute->id, 'value' => 'Go home', 'start_event' => $later->id])
            ->assertOk();

        $this->assertDatabaseHas('codex_attribute_values', [
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'start_event_id' => $project->startEvent()->id,
            'value' => '',
        ]);
    }

    public function test_set_attribute_rejects_an_unknown_or_wrong_type_attribute_and_a_foreign_event(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Location)->create(['name' => 'Climate']);
        CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Goal']);
        $foreignEvent = Event::factory()->for(Project::factory())->create();

        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => 'Mood', 'value' => 'x'])
            ->assertHasErrors(['No Character attribute matches "Mood"']);
        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => 'Climate', 'value' => 'x'])
            ->assertHasErrors(['No Character attribute matches "Climate"']);
        AvcsoServer::tool(SetCodexAttributeTool::class, ['entry' => $entry->id, 'attribute' => 'Goal', 'value' => 'x', 'start_event' => $foreignEvent->id])
            ->assertHasErrors(['start event']);

        $this->assertSame(0, $entry->attributeValues()->count());
        $this->assertSame(2, CodexAttribute::query()->where('project_id', $project->id)->count());
    }

    public function test_the_server_registers_each_codex_tool_once(): void
    {
        $tools = (new \ReflectionProperty(AvcsoServer::class, 'tools'))->getDefaultValue();

        $this->assertSame(array_values(array_unique($tools)), $tools);
        $this->assertContains(CreateCodexEntryTool::class, $tools);
        $this->assertContains(UpdateCodexEntryTool::class, $tools);
        $this->assertContains(SetCodexAttributeTool::class, $tools);
    }
}
