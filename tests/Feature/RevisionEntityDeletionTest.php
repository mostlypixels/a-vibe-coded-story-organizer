<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Deleting an entity deletes its revisions and those of its cascaded children. */
class RevisionEntityDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function revisionOf(Model $entity): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => $entity::class,
            'revisionable_id' => $entity->id,
            'project_id' => $entity->revisionProject()->id,
        ]);
    }

    /** @return array{0: Project, 1: Book, 2: Act, 3: Chapter, 4: Scene} */
    private function manuscript(?User $owner = null): array
    {
        [$project, $book] = $this->projectWithBook($owner);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        return [$project, $book, $act, $chapter, $scene];
    }

    public function test_deleting_a_scene_deletes_its_revisions_only(): void
    {
        [, , , $chapter, $scene] = $this->manuscript();
        $sibling = Scene::factory()->for($chapter)->create();

        $gone = $this->revisionOf($scene);
        $kept = $this->revisionOf($sibling);

        $scene->delete();

        $this->assertModelMissing($gone);
        $this->assertModelExists($kept);
    }

    public function test_deleting_a_project_level_entity_deletes_its_revisions(): void
    {
        [$project] = $this->projectWithBook();

        foreach ([CodexEntry::class, Event::class, Plotline::class] as $class) {
            $entity = $class::factory()->for($project)->create();
            $revision = $this->revisionOf($entity);

            $entity->delete();

            $this->assertModelMissing($revision);
        }
    }

    public function test_deleting_a_chapter_deletes_its_scenes_revisions(): void
    {
        [, , $act, $chapter, $scene] = $this->manuscript();
        $otherChapter = Chapter::factory()->for($act)->create();

        $chapterRevision = $this->revisionOf($chapter);
        $sceneRevision = $this->revisionOf($scene);
        $kept = $this->revisionOf($otherChapter);

        $chapter->delete();

        $this->assertModelMissing($chapterRevision);
        $this->assertModelMissing($sceneRevision);
        $this->assertModelExists($kept);
    }

    public function test_deleting_an_act_deletes_its_chapters_and_scenes_revisions(): void
    {
        [, $book, $act, $chapter, $scene] = $this->manuscript();
        $otherAct = Act::factory()->for($book)->create();

        $revisions = [$this->revisionOf($act), $this->revisionOf($chapter), $this->revisionOf($scene)];
        $kept = $this->revisionOf($otherAct);

        $act->delete();

        foreach ($revisions as $revision) {
            $this->assertModelMissing($revision);
        }
        $this->assertModelExists($kept);
    }

    public function test_deleting_a_book_deletes_every_revision_below_it(): void
    {
        [$project, $book, $act, $chapter, $scene] = $this->manuscript();
        $otherBook = Book::factory()->for($project)->create();

        $revisions = [
            $this->revisionOf($book),
            $this->revisionOf($act),
            $this->revisionOf($chapter),
            $this->revisionOf($scene),
        ];
        $kept = $this->revisionOf($otherBook);
        $projectRevision = $this->revisionOf($project);

        $book->delete();

        foreach ($revisions as $revision) {
            $this->assertModelMissing($revision);
        }
        $this->assertModelExists($kept);
        $this->assertModelExists($projectRevision);
    }

    public function test_scenes_moved_out_of_a_deleted_chapter_keep_their_revisions(): void
    {
        $user = User::factory()->create();
        [, $book, $act, $chapter, $scene] = $this->manuscript($user);
        $destination = Chapter::factory()->for($act)->create();

        $sceneRevision = $this->revisionOf($scene);
        $chapterRevision = $this->revisionOf($chapter);

        $this->actingAs($user)
            ->delete(route('chapters.destroy', $chapter), ['move_children_to' => $destination->id])
            ->assertRedirect(route('books.chapters.index', $book));

        $this->assertModelExists($sceneRevision);
        $this->assertModelMissing($chapterRevision);
    }

    public function test_the_cleanup_migration_deletes_orphan_revisions_only(): void
    {
        [, , , $chapter, $scene] = $this->manuscript();
        $live = Scene::factory()->for($chapter)->create();

        $orphan = $this->revisionOf($scene);
        $kept = $this->revisionOf($live);

        // A raw delete skips the model hooks, as old data did.
        DB::table('scenes')->where('id', $scene->id)->delete();

        /** @var Migration $migration */
        $migration = include database_path('migrations/2026_09_23_000000_delete_orphan_revisions.php');
        $migration->up();

        $this->assertModelMissing($orphan);
        $this->assertModelExists($kept);
    }
}
