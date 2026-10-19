<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\SaveSceneReviewTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Note;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveSceneReviewToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_then_replaces_the_review_note(): void
    {
        $project = Project::factory()->create();
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'The Well', 'contents' => 'Water rose.']);

        AvcsoServer::tool(SaveSceneReviewTool::class, ['scene' => 'The Well', 'body' => '<p>Fix the spelling.</p>'])
            ->assertOk()
            ->assertSee(['Saved review note #', '"Review: The Well"', "for scene #{$scene->id}."]);

        AvcsoServer::tool(SaveSceneReviewTool::class, ['scene' => (string) $scene->id, 'body' => '<p>All clear.</p>'])->assertOk();

        $notes = $scene->notes()->get();
        $this->assertCount(1, $notes);
        $this->assertSame('<p>All clear.</p>', $notes->first()->body);
        $this->assertSame('TODO', $notes->first()->category->name);
        $this->assertSame('Water rose.', $scene->fresh()->contents);
    }

    public function test_a_missing_body_or_scene_is_a_tool_error(): void
    {
        AvcsoServer::tool(SaveSceneReviewTool::class, ['scene' => '9999', 'body' => '<p>x</p>'])->assertHasErrors(['No scene with ID #9999 found.']);
        AvcsoServer::tool(SaveSceneReviewTool::class, ['scene' => '9999'])->assertHasErrors(['body']);
        $this->assertSame(0, Note::query()->count());
    }

    public function test_the_tool_is_named_and_has_no_contents_argument(): void
    {
        $tool = (new SaveSceneReviewTool)->toArray();

        $this->assertSame('save-scene-review', $tool['name']);
        $this->assertSame(['scene', 'project', 'body'], array_keys($tool['inputSchema']['properties']));
    }
}
