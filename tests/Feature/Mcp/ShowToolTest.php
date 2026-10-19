<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use App\Mcp\Tools\ShowTool;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

class ShowToolTest extends TestCase
{
    use RefreshDatabase;

    private function show(string $type, int $id): TestResponse
    {
        return AvcsoServer::tool(ShowTool::class, ['type' => $type, 'id' => $id]);
    }

    public function test_it_shows_a_scene_with_full_contents_event_viewpoint_and_notes(): void
    {
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create(['name' => 'The road']);
        $event = Event::factory()->for($project)->create(['title' => 'The storm', 'event_datetime' => '1200-05-01 10:00:00']);
        $hero = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $scene = Scene::factory()->for($chapter)->create([
            'name' => 'Arrival',
            'description' => '<p>She <em>arrives</em>.</p>',
            'contents' => "First paragraph.\n\nSecond paragraph, in full.",
            'event_id' => $event->id,
            'viewpoint_character_id' => $hero->id,
        ]);
        $note = Note::factory()->for($project)->create(['title' => 'Check the date', 'note_category_id' => NoteCategory::factory()->for($project)->create(['name' => 'TODO'])->id]);
        $note->linkTo($scene);

        $this->show('scene', $scene->id)
            ->assertOk()
            ->assertSee([
                "(scene #{$scene->id})",
                'Chapter: #'.$chapter->id.' The road',
                "Viewpoint character: #{$hero->id} Melusine",
                "Event: #{$event->id} The storm, 1200-05-01 10:00",
                '<p>She <em>arrives</em>.</p>',
                "- #{$note->id} Check the date [TODO]",
                "## Contents (Markdown)\nFirst paragraph.\n\nSecond paragraph, in full.",
            ]);
    }

    public function test_it_shows_a_codex_entry_with_aliases_and_attribute_values(): void
    {
        $project = Project::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine', 'description' => '<p>A fairy.</p>']);
        CodexAlias::factory()->for($entry, 'entry')->create(['alias' => 'The Serpent']);
        $attribute = CodexAttribute::factory()->for($project)->create(['name' => 'Eye color']);
        $later = Event::factory()->for($project)->create(['title' => 'The curse', 'event_datetime' => '1200-05-01 10:00:00']);
        CodexAttributeValue::factory()->create(['codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id, 'start_event_id' => $project->startEvent()->id, 'value' => 'green']);
        CodexAttributeValue::factory()->create(['codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id, 'start_event_id' => $later->id, 'value' => 'gold']);

        $this->show('codex', $entry->id)
            ->assertOk()
            ->assertSee([
                "Character #{$entry->id}: Melusine",
                'Aliases: The Serpent',
                '<p>A fairy.</p>',
                '- Eye color: green',
                '    - from The curse (1200-05-01 10:00): gold',
            ]);
    }

    public function test_it_shows_a_note_with_its_links_and_body(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create(['title' => 'Map', 'body' => '<p>See the <strong>map</strong>.</p>']);
        $plotline = Plotline::factory()->for($project)->create(['name' => 'The curse']);
        $note->linkTo($plotline);

        $this->show('note', $note->id)
            ->assertOk()
            ->assertSee(["Note #{$note->id}: Map", 'Category: none', "Plotline: #{$plotline->id} The curse", '<p>See the <strong>map</strong>.</p>']);
    }

    public function test_it_shows_project_book_act_chapter_plotline_and_event(): void
    {
        $project = Project::factory()->create(['name' => 'Melusine', 'daily_word_goal' => 500]);
        $book = $project->books()->first();
        $book->update(['author' => 'Jean d\'Arras', 'dedication' => 'For the reader.']);
        $act = Act::factory()->for($book)->create(['name' => 'Act name']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Chapter name']);
        Scene::factory()->for($chapter)->create(['name' => 'Scene name', 'contents' => 'one two']);
        $plotline = Plotline::factory()->for($project)->create(['name' => 'The curse']);
        $event = Event::factory()->for($project)->create(['title' => 'The storm', 'event_datetime' => '1200-05-01 10:00:00']);
        $plotline->events()->attach($event);

        $this->show('project', $project->id)->assertOk()
            ->assertSee(["Project #{$project->id}: Melusine", 'Daily word goal: 500', 'Words: 2 words', "- #{$book->id} Melusine (1 act)"]);
        $this->show('book', $book->id)->assertOk()
            ->assertSee(["Book #{$book->id}", 'Author: Jean d\'Arras', "## Dedication (Markdown)\nFor the reader.", '## Preface (Markdown)', "- #{$act->id} Act name"]);
        $this->show('act', $act->id)->assertOk()
            ->assertSee(["Act 1 — Act name (act #{$act->id})", "- #{$chapter->id} Chapter name (1 scene)"]);
        $this->show('chapter', $chapter->id)->assertOk()
            ->assertSee(["Chapter 1 — Chapter name (chapter #{$chapter->id})", 'Scene name [Draft, 2 words]']);
        $this->show('plotline', $plotline->id)->assertOk()
            ->assertSee(["Plotline #{$plotline->id}: The curse", "- #{$event->id} The storm, 1200-05-01 10:00"]);
        $this->show('event', $event->id)->assertOk()
            ->assertSee(["Event #{$event->id}: The storm", "Plotlines: #{$plotline->id} The curse", 'Fixed: no']);
    }

    public function test_an_unknown_type_or_id_is_a_tool_error(): void
    {
        AvcsoServer::tool(ShowTool::class, ['type' => 'dragon', 'id' => 1])->assertHasErrors(['The selected type is invalid.']);

        foreach (['project', 'book', 'act', 'chapter', 'scene', 'plotline', 'event', 'note', 'codex'] as $type) {
            $this->show($type, 9999)->assertHasErrors(['with ID #9999 found.']);
        }
    }

    public function test_the_tool_is_named_and_read_only(): void
    {
        $tool = (new ShowTool)->toArray();

        $this->assertSame('show', $tool['name']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
    }
}
