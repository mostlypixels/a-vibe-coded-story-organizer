<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\ListTool;
use App\Models\CodexAlias;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Plotline;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_plotlines_and_events(): void
    {
        $project = Project::factory()->create();
        $plotline = Plotline::factory()->for($project)->create(['name' => 'The curse', 'description' => '<p>A <strong>dark</strong> debt.</p>']);
        $event = Event::factory()->for($project)->create(['title' => 'The wedding', 'event_datetime' => '1200-05-01 10:00:00', 'is_approximate' => true]);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'plotline'])
            ->assertOk()
            ->assertSee(["- #{$plotline->id} The curse — A dark debt.", 'Main plotline (main)']);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'event'])
            ->assertSee("- #{$event->id} The wedding, about 1200-05-01 10:00");
    }

    public function test_notes_group_by_category(): void
    {
        $project = Project::factory()->create();
        $todo = NoteCategory::factory()->for($project)->create(['name' => 'TODO']);
        $inTodo = Note::factory()->for($project)->create(['title' => 'Fix the map', 'note_category_id' => $todo->id, 'body' => '<p>BODY-NOT-LISTED</p>']);
        $loose = Note::factory()->for($project)->create(['title' => 'Loose idea', 'note_category_id' => null]);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'note'])
            ->assertOk()
            ->assertSee(["## TODO (category #{$todo->id})", "- #{$inTodo->id} Fix the map", '## No category', "- #{$loose->id} Loose idea"])
            ->assertDontSee('BODY-NOT-LISTED');
    }

    public function test_it_lists_note_categories_with_counts(): void
    {
        $project = Project::factory()->create();
        $parent = NoteCategory::factory()->for($project)->create(['name' => 'Research']);
        $child = NoteCategory::factory()->for($project)->create(['name' => 'Maps', 'parent_id' => $parent->id]);
        Note::factory()->for($project)->create(['note_category_id' => $child->id]);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'note-category'])
            ->assertSee(["- #{$parent->id} Research (0 notes)", "- #{$child->id} Maps (1 note, inside #{$parent->id})"]);
    }

    public function test_it_lists_each_codex_type_with_aliases(): void
    {
        $project = Project::factory()->create();
        $hero = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        CodexAlias::factory()->for($hero, 'entry')->create(['alias' => 'The Serpent']);
        $town = CodexEntry::factory()->for($project)->location()->create(['name' => 'Lusignan']);
        $guild = CodexEntry::factory()->for($project)->organization()->create(['name' => 'The Guild']);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'character'])
            ->assertSee("- #{$hero->id} Melusine (aliases: The Serpent)")
            ->assertDontSee(['Lusignan', 'The Guild']);
        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'location'])
            ->assertSee("- #{$town->id} Lusignan");
        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'organization'])
            ->assertSee("- #{$guild->id} The Guild");
    }

    public function test_it_does_not_list_another_projects_rows(): void
    {
        $project = Project::factory()->create();
        Plotline::factory()->for(Project::factory()->create())->create(['name' => 'Foreign plotline']);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'plotline'])
            ->assertDontSee('Foreign plotline');
    }

    public function test_it_caps_at_200_rows_and_the_offset_continues(): void
    {
        $project = Project::factory()->create();

        foreach (range(1, 205) as $number) {
            CodexEntry::factory()->for($project)->character()->create(['name' => sprintf('Person %03d', $number), 'description' => null]);
        }

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'character'])
            ->assertOk()
            ->assertSee(['Person 001', 'Person 200', 'More rows exist. Call again with offset 200.'])
            ->assertDontSee('Person 201');

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'character', 'offset' => 200])
            ->assertSee(['Person 201', 'Person 205'])
            ->assertDontSee(['Person 200', 'More rows exist']);

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'character', 'offset' => 400])
            ->assertSee('No more rows.');
    }

    public function test_an_unknown_type_or_project_is_a_tool_error(): void
    {
        $project = Project::factory()->create();

        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'dragon'])
            ->assertHasErrors(['The selected type is invalid.']);
        AvcsoServer::tool(ListTool::class, ['project' => 9999, 'type' => 'plotline'])
            ->assertHasErrors(['No project with ID #9999 found.']);
        AvcsoServer::tool(ListTool::class, ['project' => $project->id, 'type' => 'plotline', 'offset' => -1])
            ->assertHasErrors();
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        $tool = (new ListTool)->toArray();

        $this->assertSame('list', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
