<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\ReferencingScenes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The two "see all" pages behind the capped reference cards.
 *
 * `codex.scenes.index` hand-builds its paginator over
 * ReferencingScenes::forEntry(), so those tests prove the page boundary matches
 * the service's own order. `scenes.codex-references.index` pages in SQL.
 */
class ReferenceListTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project, string $name, ?Event $event = null): Scene
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $attributes = ['name' => $name];

        if ($event !== null) {
            $attributes['event_id'] = $event->id;
        }

        return Scene::factory()->for($chapter)->create($attributes);
    }

    public function test_page_one_and_page_two_of_a_three_page_set_return_disjoint_rows(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $scenes = collect(range(1, 125))
            ->map(fn (int $i) => $this->sceneIn($project, sprintf('Scene %03d', $i)));
        $entry->referencingScenes()->attach($scenes->pluck('id'));

        $pageOne = $this->actingAs($user)
            ->get(route('codex.scenes.index', $entry));
        $pageTwo = $this->actingAs($user)
            ->get(route('codex.scenes.index', ['codexEntry' => $entry, 'page' => 2]));

        $pageOne->assertOk();
        $pageTwo->assertOk();

        $namesOnPageOne = $pageOne->viewData('paginator')->pluck('name')->all();
        $namesOnPageTwo = $pageTwo->viewData('paginator')->pluck('name')->all();

        $this->assertCount(50, $namesOnPageOne);
        $this->assertCount(50, $namesOnPageTwo);
        $this->assertEmpty(array_intersect($namesOnPageOne, $namesOnPageTwo));
        $this->assertSame(125, $pageOne->viewData('paginator')->total());
    }

    public function test_rows_per_page_follows_the_readers_page_size(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $response = $this->actingAs($user)->get(route('codex.scenes.index', $entry));

        $response->assertOk();
        $this->assertSame(50, $response->viewData('paginator')->perPage());
    }

    public function test_a_reader_who_never_chose_a_page_size_gets_the_configured_default(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $response = $this->actingAs($user)->get(route('codex.scenes.index', $entry));

        $response->assertOk();
        $this->assertSame(config('pagination.default'), $response->viewData('paginator')->perPage());
    }

    public function test_order_across_a_page_boundary_matches_referencing_scenes_for_entry(): void
    {
        $user = User::factory()->create(['page_size' => 2]);
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $laterEvent = Event::factory()->for($project)->create(['event_datetime' => now()->addDays(10)]);
        $earlierEvent = Event::factory()->for($project)->create(['event_datetime' => now()->addDays(2)]);

        $laterScene = $this->sceneIn($project, 'Scene at the coronation', $laterEvent);
        $earlierScene = $this->sceneIn($project, 'Scene at the betrothal', $earlierEvent);
        $unassignedScene = $this->sceneIn($project, 'Scene without an event');

        $entry->referencingScenes()->attach([$unassignedScene->id, $laterScene->id, $earlierScene->id]);

        $expectedOrder = (new ReferencingScenes)->forEntry($entry)->pluck('name')->all();

        $pageOne = $this->actingAs($user)->get(route('codex.scenes.index', $entry))
            ->viewData('paginator')->pluck('name')->all();
        $pageTwo = $this->actingAs($user)->get(route('codex.scenes.index', ['codexEntry' => $entry, 'page' => 2]))
            ->viewData('paginator')->pluck('name')->all();

        $this->assertSame($expectedOrder, array_merge($pageOne, $pageTwo));
    }

    public function test_empty_state_renders_when_the_entry_has_no_references(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $response = $this->actingAs($user)->get(route('codex.scenes.index', $entry));

        $response->assertOk();
        $response->assertSee('No scenes reference this entry yet.');
    }

    public function test_a_non_owner_gets_a_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($other)
            ->get(route('codex.scenes.index', $entry))
            ->assertForbidden();
    }

    public function test_a_single_book_project_prints_no_book_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $scene = $this->sceneIn($project, 'Only scene');
        $entry->referencingScenes()->attach($scene->id);

        $response = $this->actingAs($user)->get(route('codex.scenes.index', $entry));

        $response->assertOk();
        $response->assertDontSee(__('Book'));
    }

    public function test_a_two_book_project_prints_a_display_name_for_the_unnamed_book_and_never_a_hash(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $unnamedBook = $project->books()->first();
        $unnamedBook->update(['name' => null]);
        $unnamedBookScene = $this->sceneInBook($unnamedBook, 'Scene in the unnamed book');

        $namedBook = Book::factory()->for($project)->create(['name' => 'Book Two']);
        $namedBookScene = $this->sceneInBook($namedBook, 'Scene in book two');

        $entry->referencingScenes()->attach([$unnamedBookScene->id, $namedBookScene->id]);

        $response = $this->actingAs($user)->get(route('codex.scenes.index', $entry));

        $response->assertOk();
        $response->assertSee(__('Book'));
        $response->assertSee('Melusine');
        $response->assertSee('Book Two');
        $response->assertDontSee('#'.$unnamedBook->id, false);
    }

    public function test_scene_entry_pages_return_disjoint_rows(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'The coronation');

        $entries = collect(range(1, 125))->map(fn (int $i) => CodexEntry::factory()
            ->for($project)
            ->character()
            ->create(['name' => sprintf('Entry %03d', $i)]));
        $scene->codexReferences()->attach($entries->pluck('id'));

        $pageOne = $this->actingAs($user)
            ->get(route('scenes.codex-references.index', $scene));
        $pageTwo = $this->actingAs($user)
            ->get(route('scenes.codex-references.index', ['scene' => $scene, 'page' => 2]));

        $pageOne->assertOk();
        $pageTwo->assertOk();

        $namesOnPageOne = $pageOne->viewData('paginator')->pluck('name')->all();
        $namesOnPageTwo = $pageTwo->viewData('paginator')->pluck('name')->all();

        $this->assertCount(50, $namesOnPageOne);
        $this->assertCount(50, $namesOnPageTwo);
        $this->assertEmpty(array_intersect($namesOnPageOne, $namesOnPageTwo));
        $this->assertSame(125, $pageOne->viewData('paginator')->total());
    }

    public function test_scene_entry_rows_per_page_follows_the_readers_page_size(): void
    {
        $user = User::factory()->create(['page_size' => 50]);
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'The coronation');

        $response = $this->actingAs($user)->get(route('scenes.codex-references.index', $scene));

        $response->assertOk();
        $this->assertSame(50, $response->viewData('paginator')->perPage());
    }

    public function test_scene_entries_are_ordered_by_type_then_name(): void
    {
        $user = User::factory()->create(['page_size' => 2]);
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'The coronation');

        $keep = CodexEntry::factory()->for($project)->location()->create(['name' => 'The keep']);
        $melusine = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $albert = CodexEntry::factory()->for($project)->character()->create(['name' => 'Albert']);

        $scene->codexReferences()->attach([$keep->id, $melusine->id, $albert->id]);

        $pageOne = $this->actingAs($user)->get(route('scenes.codex-references.index', $scene))
            ->viewData('paginator')->pluck('name')->all();
        $pageTwo = $this->actingAs($user)
            ->get(route('scenes.codex-references.index', ['scene' => $scene, 'page' => 2]))
            ->viewData('paginator')->pluck('name')->all();

        $this->assertSame(['Albert', 'Melusine', 'The keep'], array_merge($pageOne, $pageTwo));
    }

    public function test_scene_entry_empty_state_renders_when_the_scene_references_nothing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'The coronation');

        $response = $this->actingAs($user)->get(route('scenes.codex-references.index', $scene));

        $response->assertOk();
        $response->assertSee('This scene references no codex entries yet.');
    }

    public function test_a_non_owner_gets_a_403_from_the_scene_entry_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $scene = $this->sceneIn($project, 'The coronation');

        $this->actingAs($other)
            ->get(route('scenes.codex-references.index', $scene))
            ->assertForbidden();
    }

    /**
     * @return array{0: Scene, 1: Collection<int, CodexEntry>}
     */
    private function sceneReferencing(Project $project, int $count): array
    {
        $scene = $this->sceneIn($project, 'The coronation');

        $entries = collect(range(1, $count))->map(fn (int $i) => CodexEntry::factory()
            ->for($project)
            ->character()
            ->create(['name' => 'Referenced entry '.chr(64 + $i)]));

        $scene->codexReferences()->attach($entries->pluck('id'));

        return [$scene, $entries];
    }

    public function test_the_scene_show_card_caps_its_rows_and_links_to_the_full_page(): void
    {
        config(['search.cap' => 5]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        [$scene] = $this->sceneReferencing($project, 6);

        $response = $this->actingAs($user)->get(route('scenes.show', $scene));

        $response->assertOk();
        $response->assertSee('Referenced entry A');
        $response->assertSee('Referenced entry E');
        $response->assertDontSee('Referenced entry F');
        $response->assertSee('See all 6 results');
    }

    public function test_the_scene_edit_card_caps_its_rows_and_links_to_the_full_page(): void
    {
        config(['search.cap' => 5]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        [$scene] = $this->sceneReferencing($project, 6);

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertOk();
        $response->assertSee('Referenced entry E');
        $response->assertDontSee('Referenced entry F');
        $response->assertSee('See all 6 results');
    }

    public function test_a_scene_card_at_the_cap_shows_no_footer_link(): void
    {
        config(['search.cap' => 5]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        [$scene] = $this->sceneReferencing($project, 5);

        $this->actingAs($user)->get(route('scenes.show', $scene))
            ->assertOk()
            ->assertDontSee('See all');

        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertDontSee('See all');
    }

    public function test_the_quick_entry_fragment_is_capped_once_the_new_entry_passes_the_cap(): void
    {
        config(['search.cap' => 5]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        [$scene] = $this->sceneReferencing($project, 5);

        // syncScene() rebuilds the pivot from the text, so every name that must
        // stay referenced has to appear in the scene content.
        $scene->update(['contents' => 'Referenced entry A, Referenced entry B, Referenced entry C, '
            .'Referenced entry D, Referenced entry E, and Referenced entry Z walk in.']);

        $response = $this->actingAs($user)->post(route('scenes.codex-entries.store', $scene), [
            'name' => 'Referenced entry Z',
            'type' => 'character',
        ]);

        $response->assertOk();

        $html = $response->json('referenced_entries_html');

        $this->assertStringContainsString('See all 6 results', $html);
        $this->assertSame(5, substr_count($html, 'Referenced entry '));
    }

    private function sceneInBook(Book $book, string $name): Scene
    {
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['name' => $name]);
    }
}
