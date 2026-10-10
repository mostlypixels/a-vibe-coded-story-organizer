<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Note;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Run the scene notes migration against rows that still have a `scenes.notes` column. */
class MoveSceneNotesToNotesTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    private Project $project;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = include database_path('migrations/2026_10_10_000003_move_scene_notes_to_notes.php');

        // Put the old column back so the rows can hold scene notes again.
        $this->migration->down();

        $this->project = Project::factory()->create();
        $act = Act::factory()->for($this->project->books()->first())->create();
        $this->chapter = Chapter::factory()->for($act)->create();
    }

    private function sceneWithNotes(string $name, ?string $notes): Scene
    {
        $scene = Scene::factory()->for($this->chapter)->create(['name' => $name]);
        DB::table('scenes')->where('id', $scene->id)->update(['notes' => $notes]);

        return $scene;
    }

    public function test_a_scene_with_notes_becomes_one_linked_note(): void
    {
        $scene = $this->sceneWithNotes('The fountain', '<p>Check the <em>date</em>.</p>');

        $this->migration->up();

        $note = Note::query()->sole();
        $this->assertSame('Notes: The fountain', $note->title);
        $this->assertSame(Note::titleForSceneNotes('The fountain'), $note->title);
        $this->assertSame('<p>Check the <em>date</em>.</p>', $note->body);
        $this->assertSame($this->project->id, $note->project_id);
        $this->assertNull($note->note_category_id);
        $this->assertSame([$note->id], $scene->notes()->pluck('notes.id')->all());
        $this->assertFalse(Schema::hasColumn('scenes', 'notes'));
    }

    public function test_a_blank_or_null_scene_note_creates_nothing(): void
    {
        $this->sceneWithNotes('Empty', '');
        $this->sceneWithNotes('Spaces', "  \n ");
        $this->sceneWithNotes('Null', null);

        $this->migration->up();

        $this->assertSame(0, Note::count());
        $this->assertSame(0, DB::table('notables')->count());
    }

    public function test_scene_notes_revisions_are_deleted_and_other_revisions_stay(): void
    {
        $scene = $this->sceneWithNotes('Kept', '<p>x</p>');
        $base = [
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $this->project->id,
        ];
        Revision::factory()->create([...$base, 'field' => 'notes']);
        $description = Revision::factory()->create([...$base, 'field' => 'description']);
        $contents = Revision::factory()->create([...$base, 'field' => 'contents']);

        $this->migration->up();

        $this->assertEqualsCanonicalizing(
            [$description->id, $contents->id],
            Revision::query()->where('revisionable_type', Scene::class)->pluck('id')->all(),
        );
    }
}
