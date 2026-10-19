<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\ListProjectsTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProjectsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_with_counts(): void
    {
        $project = Project::factory()->create(['name' => 'Melusine']);
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();
        Scene::factory()->for($chapter)->create(['contents' => 'one two three']);
        Scene::factory()->for($chapter)->create(['contents' => 'four five']);
        $project->books()->create([]);

        AvcsoServer::tool(ListProjectsTool::class)
            ->assertOk()
            ->assertSee(sprintf('#%d Melusine: 2 books, 2 scenes, 5 words', $project->id));
    }

    public function test_counts_of_one_are_singular(): void
    {
        $project = Project::factory()->create(['name' => 'Short']);
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();
        Scene::factory()->for($chapter)->create(['contents' => 'one']);

        AvcsoServer::tool(ListProjectsTool::class)
            ->assertSee('Short: 1 book, 1 scene, 1 word');
    }

    public function test_it_says_so_when_there_is_no_project(): void
    {
        AvcsoServer::tool(ListProjectsTool::class)->assertSee('No projects.');
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        AvcsoServer::tools()->assertRegistered(ListProjectsTool::class);

        $tool = (new ListProjectsTool)->toArray();

        $this->assertSame('list-projects', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
