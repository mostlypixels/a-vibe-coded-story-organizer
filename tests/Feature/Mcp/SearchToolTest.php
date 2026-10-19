<?php

namespace Tests\Feature\Mcp;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\SearchTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Services\ProjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchToolTest extends TestCase
{
    use RefreshDatabase;

    private function chapterIn(Project $project): Chapter
    {
        return Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();
    }

    public function test_it_returns_the_same_ids_as_project_search_with_a_marked_snippet(): void
    {
        $project = Project::factory()->create();
        $chapter = $this->chapterIn($project);
        $hit = Scene::factory()->for($chapter)->create(['name' => 'Dawn', 'contents' => 'The dragon slept under the hill.']);
        $second = Scene::factory()->for($chapter)->create(['name' => 'Dusk', 'contents' => 'A Dragon woke.']);
        Scene::factory()->for($chapter)->create(['name' => 'Night', 'contents' => 'Nothing here.']);

        $expected = app(ProjectSearch::class)
            ->searchDomain($project, SearchDomain::Scenes, 'dragon', SearchMode::AllTerms)
            ->map(fn ($row) => $row->entity->getKey())->all();

        $this->assertSame([$hit->id, $second->id], $expected);

        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'dragon'])
            ->assertOk()
            ->assertSee(["- #{$hit->id} Dawn in ", "- #{$second->id} Dusk in ", '[dragon]', '[Dragon]'])
            ->assertDontSee('Night');
    }

    public function test_it_searches_other_domains_and_modes(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin', 'description' => '<p>A knight of Poitou.</p>']);

        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'knight poitou', 'in' => 'characters', 'mode' => 'exact'])
            ->assertSee('No matches.');
        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'knight poitou', 'in' => 'characters', 'mode' => 'any'])
            ->assertSee("- #{$entry->id} Raymondin (Description)");
    }

    public function test_it_stays_inside_the_project(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        Scene::factory()->for($this->chapterIn($other))->create(['contents' => 'a secret dragon']);

        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'dragon'])
            ->assertSee('No matches.');
    }

    public function test_it_caps_the_rows(): void
    {
        $project = Project::factory()->create();
        Scene::factory()->count(SearchTool::LIMIT + 2)->for($this->chapterIn($project))->create(['contents' => 'dragon']);

        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'dragon'])
            ->assertSee(['('.(SearchTool::LIMIT + 2).')', 'Showing the first '.SearchTool::LIMIT]);
    }

    public function test_unknown_input_is_a_tool_error(): void
    {
        $project = Project::factory()->create();

        AvcsoServer::tool(SearchTool::class, ['project' => 9999, 'query' => 'x'])->assertHasErrors(['No project with ID #9999 found.']);
        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'x', 'in' => 'nowhere'])->assertHasErrors(['is invalid']);
        AvcsoServer::tool(SearchTool::class, ['project' => $project->id, 'query' => 'x', 'mode' => 'fuzzy'])->assertHasErrors(['is invalid']);
        AvcsoServer::tool(SearchTool::class, ['project' => $project->id])->assertHasErrors();
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        $tool = (new SearchTool)->toArray();

        $this->assertSame('search', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
