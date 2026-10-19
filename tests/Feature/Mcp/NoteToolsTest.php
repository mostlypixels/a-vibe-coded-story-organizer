<?php

namespace Tests\Feature\Mcp;

use App\Enums\RevisionOrigin;
use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\CreateNoteTool;
use App\Mcp\Tools\UpdateNoteTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteToolsTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project): Scene
    {
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();

        return Scene::factory()->for($chapter)->create(['name' => 'The Well']);
    }

    public function test_create_links_a_scene_and_a_codex_entry_on_both_sides(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project);
        $entry = CodexEntry::factory()->for($project)->character()->create();

        AvcsoServer::tool(CreateNoteTool::class, [
            'project' => $project->id,
            'title' => 'Well research',
            'body' => '<p>Wells in 1850.</p>',
            'links' => ["scene:{$scene->id}", "codex:{$entry->id}"],
        ])->assertOk()->assertSee(['Created note #', '"Well research"', "scene #{$scene->id}", "codex #{$entry->id}"]);

        $note = Note::query()->where('title', 'Well research')->firstOrFail();
        $this->assertSame($project->id, $note->project_id);
        $this->assertSame('<p>Wells in 1850.</p>', $note->body);
        $this->assertTrue($scene->notes()->whereKey($note->id)->exists());
        $this->assertTrue($entry->notes()->whereKey($note->id)->exists());
        $this->assertSame([$scene->id], $note->scenes()->pluck('scenes.id')->all());
        $this->assertSame([$entry->id], $note->codexEntries()->pluck('codex_entries.id')->all());
    }

    public function test_create_finds_a_category_by_id_or_by_name(): void
    {
        $project = Project::factory()->create();
        $category = NoteCategory::factory()->for($project)->create(['name' => 'Research']);

        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'By name', 'body' => '<p>a</p>', 'category' => 'research'])->assertOk();
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'By ID', 'body' => '<p>a</p>', 'category' => (string) $category->id])->assertOk();
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'None', 'body' => '<p>a</p>'])->assertOk();

        $this->assertSame($category->id, Note::query()->where('title', 'By name')->value('note_category_id'));
        $this->assertSame($category->id, Note::query()->where('title', 'By ID')->value('note_category_id'));
        $this->assertNull(Note::query()->where('title', 'None')->value('note_category_id'));
    }

    public function test_an_unknown_category_fails_and_creates_nothing(): void
    {
        $project = Project::factory()->create();
        $foreign = NoteCategory::factory()->for(Project::factory()->create())->create(['name' => 'Elsewhere']);

        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X', 'body' => '<p>a</p>', 'category' => 'Planning'])
            ->assertHasErrors(['No note category matches "Planning"']);
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X', 'body' => '<p>a</p>', 'category' => (string) $foreign->id])
            ->assertHasErrors(['No note category matches']);

        $this->assertSame(0, NoteCategory::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, Note::query()->where('project_id', $project->id)->count());
    }

    public function test_a_link_target_from_another_project_fails_validation(): void
    {
        $project = Project::factory()->create();
        $foreignScene = $this->sceneIn(Project::factory()->create());

        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X', 'body' => '<p>a</p>', 'links' => ["scene:{$foreignScene->id}"]])
            ->assertHasErrors(['links.0']);
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X', 'body' => '<p>a</p>', 'links' => ['note:1']])
            ->assertHasErrors(['links.0']);
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X', 'body' => '<p>a</p>', 'links' => ['nonsense']])
            ->assertHasErrors(['links.0']);

        $this->assertSame(0, Note::query()->count());
    }

    public function test_create_needs_a_title_a_body_and_a_real_project(): void
    {
        $project = Project::factory()->create();

        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => '', 'body' => '<p>a</p>'])->assertHasErrors(['title']);
        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'X'])->assertHasErrors(['body']);
        AvcsoServer::tool(CreateNoteTool::class, ['project' => 9999, 'title' => 'X', 'body' => '<p>a</p>'])->assertHasErrors(['No project with ID #9999 found.']);
    }

    public function test_script_in_the_body_is_stored_sanitized(): void
    {
        $project = Project::factory()->create();

        AvcsoServer::tool(CreateNoteTool::class, ['project' => $project->id, 'title' => 'Bad', 'body' => '<p>Safe</p><script>alert(1)</script>'])->assertOk();
        $note = Note::query()->where('title', 'Bad')->firstOrFail();

        $this->assertStringNotContainsString('<script', $note->body);
        $this->assertStringContainsString('Safe', $note->body);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'body' => '<p>Again</p><script>alert(2)</script>'])->assertOk();

        $this->assertStringNotContainsString('<script', $note->fresh()->body);
    }

    public function test_updating_the_body_records_one_claude_revision(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create(['body' => '<p>Old.</p>']);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'body' => '<p>New.</p>'])->assertOk()->assertSee(["Updated note #{$note->id}", 'body']);
        $rowsAfterChange = Revision::query()->count();
        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'body' => '<p>New.</p>'])->assertOk();

        $this->assertSame($rowsAfterChange, Revision::query()->count());
        $revisions = Revision::query()->where('field', 'body')->where('origin', RevisionOrigin::Manual)->get();
        $this->assertCount(1, $revisions);
        $this->assertSame('<p>New.</p>', $revisions->first()->value);
        $this->assertSame('Claude', $revisions->first()->label);
        $this->assertSame($project->user_id, $revisions->first()->user_id);
    }

    public function test_only_the_sent_fields_change(): void
    {
        $project = Project::factory()->create();
        $category = NoteCategory::factory()->for($project)->create(['name' => 'Ideas']);
        $scene = $this->sceneIn($project);
        $note = Note::factory()->for($project)->create(['title' => 'Old', 'body' => '<p>Keep.</p>', 'note_category_id' => $category->id]);
        $note->linkTo($scene);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'title' => 'New'])->assertOk();

        $note->refresh();
        $this->assertSame('New', $note->title);
        $this->assertSame('<p>Keep.</p>', $note->body);
        $this->assertSame($category->id, $note->note_category_id);
        $this->assertCount(1, $note->scenes);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Manual)->count());
    }

    public function test_update_sets_and_clears_the_category(): void
    {
        $project = Project::factory()->create();
        $category = NoteCategory::factory()->for($project)->create(['name' => 'Ideas']);
        $note = Note::factory()->for($project)->create();

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'category' => 'Ideas'])->assertOk();
        $this->assertSame($category->id, $note->fresh()->note_category_id);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'category' => null])->assertOk();
        $this->assertNull($note->fresh()->note_category_id);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'category' => 'Nope'])->assertHasErrors(['No note category matches']);
    }

    public function test_sent_links_replace_the_link_set(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project);
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $note = Note::factory()->for($project)->create();
        $note->linkTo($scene);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'links' => ["codex:{$entry->id}"]])->assertOk();

        $note->refresh();
        $this->assertCount(0, $note->scenes);
        $this->assertCount(1, $note->codexEntries);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'links' => ['scene:'.$scene->id, 'scene:'.$scene->id]])->assertOk();

        $note->refresh();
        $this->assertCount(1, $note->scenes);
        $this->assertCount(0, $note->codexEntries);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'links' => []])->assertOk();

        $this->assertSame(0, $note->scenes()->count());
    }

    public function test_update_rejects_a_foreign_link_and_keeps_the_old_links(): void
    {
        $project = Project::factory()->create();
        $scene = $this->sceneIn($project);
        $foreign = $this->sceneIn(Project::factory()->create());
        $note = Note::factory()->for($project)->create();
        $note->linkTo($scene);

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'title' => 'Changed', 'links' => ["scene:{$foreign->id}"]])->assertHasErrors(['links.0']);

        $this->assertCount(1, $note->fresh()->scenes);
        $this->assertNotSame('Changed', $note->fresh()->title);
    }

    public function test_update_errors(): void
    {
        $note = Note::factory()->create();

        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id])->assertHasErrors(['Send at least one field']);
        AvcsoServer::tool(UpdateNoteTool::class, ['note' => $note->id, 'title' => ''])->assertHasErrors(['title']);
        AvcsoServer::tool(UpdateNoteTool::class, ['note' => 9999, 'title' => 'X'])->assertHasErrors(['No note with ID #9999 found.']);
    }

    public function test_the_tools_are_registered_and_not_destructive(): void
    {
        $create = (new CreateNoteTool)->toArray();
        $update = (new UpdateNoteTool)->toArray();

        $this->assertSame('create-note', $create['name']);
        $this->assertSame('update-note', $update['name']);
        $this->assertArrayNotHasKey('readOnlyHint', $create['annotations']);
        $this->assertArrayNotHasKey('destructiveHint', $update['annotations']);
        $this->assertSame('array', $create['inputSchema']['properties']['links']['type']);
    }
}
