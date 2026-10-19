<?php

namespace Tests\Feature\Mcp;

use App\Enums\CodexEntryType;
use App\Enums\RevisionOrigin;
use App\Enums\SceneStatus;
use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\UpdateSceneTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSceneToolTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project, array $attributes = []): Scene
    {
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();

        return Scene::factory()->for($chapter)->create($attributes + ['name' => 'The Well', 'contents' => 'Water rose to the rim.']);
    }

    public function test_a_description_change_leaves_the_text_and_other_fields_alone(): void
    {
        $project = Project::factory()->create();
        $event = Event::factory()->for($project)->create();
        $scene = $this->sceneIn($project, ['description' => '', 'status' => SceneStatus::ToEdit, 'event_id' => $event->id]);
        $before = $scene->fresh()->only(['name', 'contents', 'word_count', 'status', 'event_id', 'viewpoint_character_id', 'chapter_id', 'position']);

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'description' => '<p>A dry well.</p>'])
            ->assertOk()
            ->assertSee("Updated scene #{$scene->id} The Well: description.");

        $scene->refresh();
        $this->assertSame('<p>A dry well.</p>', $scene->description);
        $this->assertSame($before, $scene->only(array_keys($before)));
    }

    public function test_a_changed_description_records_one_claude_revision_and_an_unchanged_one_none(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project, ['description' => '<p>Old.</p>']);

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'description' => '<p>New.</p>'])->assertOk();
        $rowsAfterChange = Revision::query()->count();
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'description' => '<p>New.</p>'])->assertOk();

        $this->assertSame($rowsAfterChange, Revision::query()->count());
        $revisions = Revision::query()->where('field', 'description')->where('origin', RevisionOrigin::Manual)->get();
        $this->assertCount(1, $revisions);
        $this->assertSame('<p>New.</p>', $revisions->first()->value);
        $this->assertSame('Claude', $revisions->first()->label);
        $this->assertSame(RevisionOrigin::Manual, $revisions->first()->origin);
        $this->assertSame($project->user_id, $revisions->first()->user_id);
        $this->assertSame(0, Revision::query()->where('field', 'contents')->count());
    }

    public function test_it_sets_and_clears_the_event_and_viewpoint(): void
    {
        $project = Project::factory()->create();
        $event = Event::factory()->for($project)->create();
        $character = CodexEntry::factory()->for($project)->character()->create();
        $scene = $this->sceneIn($project);

        AvcsoServer::tool(UpdateSceneTool::class, [
            'scene' => (string) $scene->id, 'name' => 'The Dry Well', 'status' => 'final', 'event' => $event->id, 'viewpoint' => $character->id,
        ])->assertOk();

        $scene->refresh();
        $this->assertSame('The Dry Well', $scene->name);
        $this->assertSame(SceneStatus::Final, $scene->status);
        $this->assertSame($event->id, $scene->event_id);
        $this->assertSame($character->id, $scene->viewpoint_character_id);

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'event' => null, 'viewpoint' => null])->assertOk();

        $scene->refresh();
        $this->assertNull($scene->event_id);
        $this->assertNull($scene->viewpoint_character_id);
        $this->assertSame('The Dry Well', $scene->name);
    }

    public function test_an_event_or_viewpoint_from_another_project_fails_validation(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $scene = $this->sceneIn($project);
        $foreignEvent = Event::factory()->for($other)->create();
        $foreignCharacter = CodexEntry::factory()->for($other)->character()->create();
        $location = CodexEntry::factory()->for($project)->create(['type' => CodexEntryType::Location]);

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'event' => $foreignEvent->id])->assertHasErrors(['event']);
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'viewpoint' => $foreignCharacter->id])->assertHasErrors(['viewpoint']);
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'viewpoint' => $location->id])->assertHasErrors(['viewpoint']);

        $this->assertNull($scene->fresh()->event_id);
        $this->assertNull($scene->fresh()->viewpoint_character_id);
    }

    public function test_invalid_values_and_an_empty_call_are_tool_errors(): void
    {
        $scene = $this->sceneIn(Project::factory()->create());

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'status' => 'lost'])->assertHasErrors(['status']);
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'name' => ''])->assertHasErrors(['title']);
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id])->assertHasErrors(['Send at least one field']);
        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => '9999', 'name' => 'X'])->assertHasErrors(['No scene with ID #9999 found.']);
    }

    public function test_contents_is_not_an_argument_and_is_ignored_when_sent(): void
    {
        $scene = $this->sceneIn(Project::factory()->create());
        $tool = (new UpdateSceneTool)->toArray();

        $this->assertSame('update-scene', $tool['name']);
        $this->assertArrayNotHasKey('contents', $tool['inputSchema']['properties']);
        $this->assertArrayNotHasKey('readOnlyHint', $tool['annotations']);

        AvcsoServer::tool(UpdateSceneTool::class, ['scene' => (string) $scene->id, 'name' => 'X', 'contents' => 'Gone.'])->assertOk();

        $this->assertSame('Water rose to the rim.', $scene->fresh()->contents);
    }
}
