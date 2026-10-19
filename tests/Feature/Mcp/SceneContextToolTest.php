<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\SceneContextTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneContextToolTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project, string $name, string $contents = 'Some text.'): Scene
    {
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();

        return Scene::factory()->for($chapter)->create(['name' => $name, 'contents' => $contents]);
    }

    public function test_it_prints_the_review_context_by_id_or_name(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project, 'The Well', 'Water rose.');

        foreach ([(string) $scene->id, 'The Well', 'well'] as $reference) {
            AvcsoServer::tool(SceneContextTool::class, ['scene' => $reference, 'project' => $project->id])
                ->assertOk()
                ->assertSee(["Project: {$project->name} (ID {$project->id})", "Scene: The Well (ID {$scene->id}", 'Water rose.']);
        }
    }

    public function test_full_controls_how_many_earlier_scenes_print_their_text(): void
    {
        $project = Project::factory()->create();
        $this->sceneIn($project, 'First', 'FIRST-TEXT');
        $this->sceneIn($project, 'Second', 'SECOND-TEXT');
        $third = $this->sceneIn($project, 'Third', 'THIRD-TEXT');

        AvcsoServer::tool(SceneContextTool::class, ['scene' => (string) $third->id, 'full' => 0])
            ->assertDontSee(['FIRST-TEXT', 'SECOND-TEXT']);
        AvcsoServer::tool(SceneContextTool::class, ['scene' => (string) $third->id, 'full' => 1])
            ->assertSee('SECOND-TEXT')
            ->assertDontSee('FIRST-TEXT');
    }

    public function test_several_name_matches_return_the_list_as_a_tool_error(): void
    {
        $project = Project::factory()->create();
        $one = $this->sceneIn($project, 'Storm at sea');
        $two = $this->sceneIn($project, 'Storm on land');

        AvcsoServer::tool(SceneContextTool::class, ['scene' => 'storm'])
            ->assertHasErrors(['Several scenes match "storm"', "- #{$one->id} Storm at sea", "- #{$two->id} Storm on land"]);
    }

    public function test_the_project_argument_limits_the_name_search(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $this->sceneIn($other, 'Storm');

        AvcsoServer::tool(SceneContextTool::class, ['scene' => 'Storm', 'project' => $project->id])
            ->assertHasErrors(['No scene matches "Storm".']);
    }

    public function test_an_unknown_scene_or_project_is_a_tool_error(): void
    {
        AvcsoServer::tool(SceneContextTool::class, ['scene' => '9999'])->assertHasErrors(['No scene with ID #9999 found.']);
        AvcsoServer::tool(SceneContextTool::class, ['scene' => 'x', 'project' => 9999])->assertHasErrors(['No project with ID #9999 found.']);
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        $tool = (new SceneContextTool)->toArray();

        $this->assertSame('scene-context', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
