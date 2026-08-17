<?php

namespace Tests\Unit\Services;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Services\ProjectRevisionsBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Services\ProjectRevisionsBrowser — the tree behind the Tools ▸ Revisions
 * sidebar: every entity/field in a project that has revision history, grouped
 * by type, with per-field counts. Only revised entities/fields appear.
 */
class ProjectRevisionsBrowserTest extends TestCase
{
    use RefreshDatabase;

    private ProjectRevisionsBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = new ProjectRevisionsBrowser;
    }

    private function revisionFor(string $type, int $id, int $projectId, string $field, int $count): void
    {
        Revision::factory()->count($count)->create([
            'revisionable_type' => $type,
            'revisionable_id' => $id,
            'project_id' => $projectId,
            'field' => $field,
        ]);
    }

    public function test_tree_groups_revised_entities_by_type_with_per_field_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        // A project-level field, an act, and a scene (reached through
        // chapter → act → project) all with history.
        $act = Act::factory()->for($book)->create(['name' => 'Act Alpha']);
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Scene One']);

        $this->revisionFor(Project::class, $project->id, $project->id, 'description', 1);
        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 2);
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'description', 1);
        $this->revisionFor(Scene::class, $scene->id, $project->id, 'notes', 3);

        $tree = $this->browser->tree($project);

        // Groups appear in the declared order, and only the ones with revisions.
        $this->assertSame(['Project', 'Acts', 'Scenes'], $tree->pluck('label')->all());

        // The Scene lists Description before Notes (registry field order), with
        // the right counts; its never-revised `contents` field is absent.
        $sceneGroup = $tree->firstWhere('type', 'scene');
        $sceneEntity = $sceneGroup->books->first()->entities->firstWhere('id', $scene->id);
        $this->assertSame(['Description', 'Notes'], $sceneEntity->fields->pluck('label')->all());
        $this->assertSame([1, 3], $sceneEntity->fields->pluck('count')->all());
    }

    public function test_entities_without_revisions_are_absent(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $revised = Act::factory()->for($book)->create(['name' => 'Revised Act']);
        Act::factory()->for($book)->create(['name' => 'Untouched Act']);

        $this->revisionFor(Act::class, $revised->id, $project->id, 'description', 1);

        $tree = $this->browser->tree($project);

        $actEntities = $tree->firstWhere('type', 'act')->books->first()->entities;
        $this->assertSame(['Revised Act'], $actEntities->pluck('name')->all());
    }

    public function test_a_field_leaf_links_to_its_history_page(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 1);

        $leaf = $this->browser->tree($project)
            ->firstWhere('type', 'act')
            ->books->first()
            ->entities->first()
            ->fields->first();

        $this->assertSame(
            route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            $leaf->url,
        );
    }

    public function test_acts_chapters_and_scenes_are_grouped_under_their_own_book(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);

        $actOne = Act::factory()->for($firstBook)->create(['name' => 'Act One']);
        $chapterOne = Chapter::factory()->for($actOne)->create(['name' => 'Chapter One']);
        $sceneOne = Scene::factory()->for($chapterOne)->create(['name' => 'Scene One']);

        $actTwo = Act::factory()->for($secondBook)->create(['name' => 'Act Two']);
        $chapterTwo = Chapter::factory()->for($actTwo)->create(['name' => 'Chapter Two']);
        $sceneTwo = Scene::factory()->for($chapterTwo)->create(['name' => 'Scene Two']);

        $this->revisionFor(Act::class, $actOne->id, $project->id, 'description', 1);
        $this->revisionFor(Act::class, $actTwo->id, $project->id, 'description', 1);
        $this->revisionFor(Chapter::class, $chapterOne->id, $project->id, 'description', 1);
        $this->revisionFor(Chapter::class, $chapterTwo->id, $project->id, 'description', 1);
        $this->revisionFor(Scene::class, $sceneOne->id, $project->id, 'notes', 1);
        $this->revisionFor(Scene::class, $sceneTwo->id, $project->id, 'notes', 1);

        $tree = $this->browser->tree($project);

        foreach (['act' => ['Act One', 'Act Two'], 'chapter' => ['Chapter One', 'Chapter Two'], 'scene' => ['Scene One', 'Scene Two']] as $type => [$firstName, $secondName]) {
            $books = $tree->firstWhere('type', $type)->books;

            $this->assertSame(
                [$firstBook->displayName(), 'Volume Two'],
                $books->pluck('name')->all(),
                "book headings for {$type}",
            );
            $this->assertSame([$firstName], $books->firstWhere('id', $firstBook->id)->entities->pluck('name')->all());
            $this->assertSame([$secondName], $books->firstWhere('id', $secondBook->id)->entities->pluck('name')->all());
        }
    }

    public function test_book_groups_are_ordered_by_book_position_not_creation_order(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Volume Two']);

        // Swap positions so the book created second sorts first, proving the
        // order comes from `position`, not id/creation order.
        $firstBook->update(['position' => 2]);
        $secondBook->update(['position' => 1]);

        $actInFirst = Act::factory()->for($firstBook)->create();
        $actInSecond = Act::factory()->for($secondBook)->create();

        $this->revisionFor(Act::class, $actInFirst->id, $project->id, 'description', 1);
        $this->revisionFor(Act::class, $actInSecond->id, $project->id, 'description', 1);

        $bookIds = $this->browser->tree($project)->firstWhere('type', 'act')->books->pluck('id')->all();

        $this->assertSame([$secondBook->id, $firstBook->id], $bookIds);
    }

    public function test_a_sole_unnamed_book_still_gets_a_heading(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();

        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 1);

        $bookGroup = $this->browser->tree($project)->firstWhere('type', 'act')->books->first();

        $this->assertNull($book->name);
        $this->assertSame('Melusine', $bookGroup->name);
    }

    public function test_project_scoped_types_stay_outside_the_book_grouping(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->revisionFor(Project::class, $project->id, $project->id, 'description', 1);

        $projectGroup = $this->browser->tree($project)->firstWhere('type', 'project');

        // One ungrouped bucket, not a book heading: the project is not scoped
        // to any book, so grouping it would be meaningless.
        $this->assertCount(1, $projectGroup->books);
        $this->assertNull($projectGroup->books->first()->id);
        $this->assertNull($projectGroup->books->first()->name);
    }

    public function test_a_revised_book_appears_as_its_own_ungrouped_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $book->update(['name' => 'Volume One']);

        $this->revisionFor(Book::class, $book->id, $project->id, 'dedication', 2);

        $bookGroup = $this->browser->tree($project)->firstWhere('type', 'book');

        $this->assertNotNull($bookGroup, 'a revised book must reach the sidebar');
        $this->assertSame('Books', $bookGroup->label);

        // A book is not scoped to a book: one ungrouped bucket, no heading.
        $this->assertCount(1, $bookGroup->books);
        $this->assertNull($bookGroup->books->first()->id);

        $entity = $bookGroup->books->first()->entities->first();
        $this->assertSame('Volume One', $entity->name);
        $this->assertSame(2, $entity->fields->first()->count);
        $this->assertSame(
            route('revisions.index', ['entity' => 'book', 'id' => $book->id, 'field' => 'dedication']),
            $entity->fields->first()->url,
        );
    }

    public function test_an_unnamed_book_is_listed_under_the_project_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        $this->revisionFor(Book::class, $book->id, $project->id, 'preface', 1);

        $entity = $this->browser->tree($project)->firstWhere('type', 'book')->books->first()->entities->first();

        $this->assertNull($book->name);
        $this->assertSame('Melusine', $entity->name);
    }

    public function test_an_empty_project_yields_an_empty_tree(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->assertTrue($this->browser->tree($project)->isEmpty());
    }

    public function test_the_tree_never_hydrates_the_revision_value(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $this->revisionFor(Act::class, $act->id, $project->id, 'description', 1);

        DB::enableQueryLog();
        $this->browser->tree($project);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The list-query invariant: the browser only needs coordinates + counts,
        // so the heavy `value` column must never be selected by any of its queries.
        foreach ($queries as $query) {
            $this->assertStringNotContainsStringIgnoringCase('value', $query['query']);
        }
    }
}
