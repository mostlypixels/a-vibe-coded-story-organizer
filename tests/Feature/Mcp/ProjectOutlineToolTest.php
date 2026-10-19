<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\ProjectOutlineTool;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Support\StoryNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectOutlineToolTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<Scene> */
    private function fillBook(Book $book, int $acts = 2): array
    {
        $scenes = [];

        for ($a = 1; $a <= $acts; $a++) {
            $act = Act::factory()->for($book)->create(['name' => "Act name {$a}", 'position' => $a]);

            for ($c = 1; $c <= 2; $c++) {
                $chapter = Chapter::factory()->for($act)->create(['name' => "Chapter name {$a}.{$c}", 'position' => $c]);
                $scenes[] = Scene::factory()->for($chapter)->create(['contents' => 'one two three', 'position' => 1]);
            }
        }

        return $scenes;
    }

    public function test_it_prints_the_tree_with_numbers_that_match_story_numbering(): void
    {
        $project = Project::factory()->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $scenes = $this->fillBook($book);

        $numbering = StoryNumbering::forBook($book);

        $response = AvcsoServer::tool(ProjectOutlineTool::class, ['project' => $project->id])
            ->assertOk()
            ->assertSee(["# Melusine (#{$project->id})", "(book #{$book->id}, 12 words)", 'Act 2 — Act name 2', 'Chapter 4 — Chapter name 2.2']);

        foreach ($scenes as $scene) {
            $response->assertSee("- #{$scene->id} Scene {$numbering->scene($scene)} — {$scene->name} [Draft, 3 words]");
        }

        $this->assertSame(4, $numbering->scene($scenes[3]));
    }

    public function test_a_book_argument_prints_only_that_book(): void
    {
        $project = Project::factory()->create(['name' => 'Saga']);
        $first = $project->books()->first();
        $second = $project->books()->create(['name' => 'Second volume']);
        $this->fillBook($first);
        $this->fillBook($second, 1);

        AvcsoServer::tool(ProjectOutlineTool::class, ['project' => $project->id, 'book' => $second->id])
            ->assertOk()
            ->assertSee(['Second volume', 'Act 1 — Act name 1', 'Scene 2'])
            ->assertDontSee('Act 2');
    }

    public function test_an_unknown_project_or_a_book_of_another_project_is_a_tool_error(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();

        AvcsoServer::tool(ProjectOutlineTool::class, ['project' => 9999])
            ->assertHasErrors(['No project with ID #9999 found.']);

        AvcsoServer::tool(ProjectOutlineTool::class, ['project' => $project->id, 'book' => $other->books()->first()->id])
            ->assertHasErrors(['No book with ID']);
    }

    public function test_it_never_reads_scene_contents_and_does_not_query_per_book(): void
    {
        $project = Project::factory()->create();
        $this->fillBook($project->books()->first());

        $queries = function () use ($project): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            AvcsoServer::tool(ProjectOutlineTool::class, ['project' => $project->id])->assertOk();

            return array_column(DB::getQueryLog(), 'query');
        };

        $few = $queries();

        foreach ([1, 2, 3] as $number) {
            $this->fillBook($project->books()->create(['name' => "Book {$number}"]));
        }

        $many = $queries();

        $this->assertCount(count($few), $many);
        $this->assertSame([], array_filter($many, fn (string $sql) => str_contains($sql, 'contents')));
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        $tool = (new ProjectOutlineTool)->toArray();

        $this->assertSame('project-outline', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
