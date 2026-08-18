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

/** Fail when Blade emits an uncompiled component tag as literal text. */
class BladeComponentCompilationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_emits_an_uncompiled_component_tag(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        // Render both enabled and disabled book controls.
        $secondBook = Book::factory()->for($project)->create();
        Act::factory()->for($secondBook)->create();

        // Render both enabled and disabled manuscript controls.
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
