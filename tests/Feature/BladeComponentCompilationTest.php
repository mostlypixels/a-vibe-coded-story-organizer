<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every server-rendered page compiles all of its Blade components.
 *
 * ## Why a whole test for one assertion
 *
 * When Blade's component-tag compiler cannot match an `<x-…>` tag, it does not
 * raise anything — it leaves the tag on the page as **literal text**, attributes
 * and all. So the page still returns 200, every `assertSee` for the surrounding
 * copy still passes, and the writer gets `<x-icon-button type="submit" …/>` printed
 * where a button should be. Nothing else in the suite can see that.
 *
 * The known cause is a Blade **directive** used as an attribute inside a component
 * tag — `@disabled($loop->first)`, `@if ($href) href="…" @endif` — which reads
 * perfectly naturally and works fine on a plain `<button>`. Bound attributes
 * (`:disabled="$loop->first"`) are the equivalent that compiles; the attribute bag
 * already drops `false`/`null` and expands `true` to `key="key"`.
 *
 * {@see IconButtonComponentTest} guards the components themselves. This guards the
 * ~20 pages that *call* them, which is where the next instance will be written.
 *
 * Add a URL here when adding a server-rendered page. Fixtures exist only so index
 * pages have rows: an empty table renders none of the per-row controls, which are
 * exactly the components most likely to carry a directive.
 */
class BladeComponentCompilationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_emits_an_uncompiled_component_tag(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        // A second book, with acts of its own, so the books index/edit "move or
        // delete" dialogs have a destination to offer, and their reorder buttons
        // render both enabled and disabled (first/last row).
        $secondBook = Book::factory()->for($project)->create();
        Act::factory()->for($secondBook)->create();

        // Two acts and two chapters so the "move or delete" dialog has a
        // destination to offer, and the reorder buttons render both enabled and
        // disabled (first/last row).
        $act = Act::factory()->for($book)->create();
        $secondAct = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Chapter::factory()->for($secondAct)->create();
        $scene = Scene::factory()->for($chapter)->create();
        Scene::factory()->for($chapter)->create();
        $codexEntry = CodexEntry::factory()->for($project)->create();

        foreach ($this->pages($project, $book, $act, $chapter, $scene, $codexEntry) as $url) {
            $response = $this->actingAs($user)->get($url);

            $response->assertSuccessful();

            $this->assertStringNotContainsString(
                '<x-',
                $response->getContent(),
                "An uncompiled Blade component tag was rendered as text on {$url}. "
                .'The usual cause is a Blade directive (@disabled, @if) used as an attribute '
                .'inside an <x-…> tag — use a bound attribute (:disabled="…") instead.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private function pages(
        Project $project,
        Book $book,
        Act $act,
        Chapter $chapter,
        Scene $scene,
        CodexEntry $codexEntry,
    ): array {
        return [
            route('dashboard'),
            route('projects.show', $project),
            route('projects.edit', $project),
            route('projects.books.index', $project),
            route('projects.books.create', $project),
            route('books.edit', $book),
            route('books.story.overview', $book),
            route('projects.search.index', [$project, 'q' => 'a']),
            route('projects.revisions.index', $project),
            route('books.acts.index', $book),
            route('books.chapters.index', $book),
            route('books.scenes.index', $book),
            route('projects.plotlines.index', $project),
            route('projects.events.index', $project),
            route('projects.codex-attributes.index', $project),
            route('acts.edit', $act),
            route('chapters.edit', $chapter),
            route('scenes.edit', $scene),
            route('codex.edit', $codexEntry),
            route('admin.settings.edit'),
            route('admin.data.export-project'),
            route('admin.data.export-ebook'),
            route('admin.data.import.index'),
            route('admin.revisions.edit'),
            route('profile.edit'),
        ];
    }
}
