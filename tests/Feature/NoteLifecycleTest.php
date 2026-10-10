<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Notable;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** A project with one book, act, chapter and scene. */
    private function story(): array
    {
        $project = Project::factory()->create();
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        return [$project, $book, $act, $chapter, $scene];
    }

    public function test_a_note_links_to_every_linkable_type(): void
    {
        [$project, $book, $act, $chapter, $scene] = $this->story();
        $event = Event::factory()->for($project)->create();
        $plotline = Plotline::factory()->for($project)->create();
        $codex = CodexEntry::factory()->for($project)->create();
        $note = Note::factory()->for($project)->create();

        foreach ([$book, $act, $chapter, $scene, $event, $plotline, $codex] as $target) {
            $target->notes()->attach($note);
        }

        $this->assertSame(7, Notable::count());
        $this->assertTrue($note->books->contains($book));
        $this->assertTrue($note->acts->contains($act));
        $this->assertTrue($note->chapters->contains($chapter));
        $this->assertTrue($note->scenes->contains($scene));
        $this->assertTrue($note->events->contains($event));
        $this->assertTrue($note->plotlines->contains($plotline));
        $this->assertTrue($note->codexEntries->contains($codex));
        $this->assertTrue($scene->notes()->get()->contains($note));
    }

    public function test_the_body_is_sanitized_on_write(): void
    {
        $note = Note::factory()->create(['body' => '<p>Safe</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('script', $note->fresh()->body);
        $this->assertStringContainsString('Safe', $note->fresh()->body);
    }

    public function test_deleting_a_linkable_removes_its_links_and_keeps_the_note(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create();
        [$event, $plotline, $codex] = [
            Event::factory()->for($project)->create(),
            Plotline::factory()->for($project)->create(),
            CodexEntry::factory()->for($project)->create(),
        ];
        [, , , , $scene] = $this->story();

        foreach ([$event, $plotline, $codex, $scene] as $target) {
            $target->notes()->attach($note);
            $target->delete();
        }

        $this->assertSame(0, Notable::count());
        $this->assertNotNull($note->fresh());
    }

    public function test_deleting_a_book_removes_the_links_of_every_cascaded_child(): void
    {
        [$project, $book, $act, $chapter, $scene] = $this->story();
        $note = Note::factory()->for($project)->create();
        $other = Note::factory()->for($project)->create();
        [$otherBook, $otherAct, $otherChapter, $otherScene] = $this->otherBranch($project);

        foreach ([$book, $act, $chapter, $scene] as $target) {
            $target->notes()->attach($note);
        }
        $otherScene->notes()->attach($other);

        $book->delete();

        $this->assertSame(0, Notable::where('note_id', $note->id)->count());
        $this->assertSame(1, Notable::where('note_id', $other->id)->count());
        $this->assertNotNull($note->fresh());
    }

    public function test_deleting_an_act_removes_the_links_of_its_chapters_and_scenes(): void
    {
        [$project, , $act, $chapter, $scene] = $this->story();
        $note = Note::factory()->for($project)->create();
        $chapter->notes()->attach($note);
        $scene->notes()->attach($note);

        $act->delete();

        $this->assertSame(0, Notable::count());
        $this->assertNotNull($note->fresh());
    }

    public function test_deleting_a_chapter_removes_the_links_of_its_scenes(): void
    {
        [$project, , , $chapter, $scene] = $this->story();
        $note = Note::factory()->for($project)->create();
        $scene->notes()->attach($note);

        $chapter->delete();

        $this->assertSame(0, Notable::count());
        $this->assertNotNull($note->fresh());
    }

    public function test_deleting_a_project_removes_notes_categories_and_links(): void
    {
        [$project, , , , $scene] = $this->story();
        $category = NoteCategory::factory()->for($project)->create();
        $note = Note::factory()->for($project)->create(['note_category_id' => $category->id]);
        $scene->notes()->attach($note);

        $project->delete();

        $this->assertSame(0, Note::count());
        $this->assertSame(0, NoteCategory::count());
        $this->assertSame(0, Notable::count());
    }

    public function test_deleting_a_note_removes_its_links_and_revisions(): void
    {
        [$project, , , , $scene] = $this->story();
        $note = Note::factory()->for($project)->create();
        $scene->notes()->attach($note);
        Revision::factory()->create([
            'revisionable_type' => Note::class,
            'revisionable_id' => $note->id,
            'field' => 'body',
            'project_id' => $project->id,
        ]);

        $note->delete();

        $this->assertSame(0, Notable::count());
        $this->assertSame(0, Revision::where('revisionable_type', Note::class)->count());
        $this->assertNotNull($scene->fresh());
    }

    public function test_deleting_a_category_nulls_the_note_category_as_a_safety_net(): void
    {
        $category = NoteCategory::factory()->create();
        $note = Note::factory()->create([
            'project_id' => $category->project_id,
            'note_category_id' => $category->id,
        ]);

        $category->delete();

        $this->assertNull($note->fresh()->note_category_id);
    }

    public function test_category_depth_counts_levels_from_the_root(): void
    {
        $project = Project::factory()->create();
        $root = NoteCategory::factory()->for($project)->create();
        $child = NoteCategory::factory()->for($project)->create(['parent_id' => $root->id]);
        $grandchild = NoteCategory::factory()->for($project)->create(['parent_id' => $child->id]);

        $this->assertSame(1, $root->depth());
        $this->assertSame(2, $child->depth());
        $this->assertSame(3, $grandchild->depth());
    }

    public function test_the_project_exposes_its_notes_and_categories(): void
    {
        $project = Project::factory()->create();
        Note::factory()->for($project)->create();
        NoteCategory::factory()->for($project)->create();
        Note::factory()->create();

        $this->assertCount(1, $project->notes);
        $this->assertCount(1, $project->noteCategories);
    }

    /** A second book in the project, with its own act, chapter and scene. */
    private function otherBranch(Project $project): array
    {
        $book = Book::factory()->for($project)->create();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        return [$book, $act, $chapter, $scene];
    }
}
