<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Index searches treat `%` and `_` as plain text, and a query value sent as an
 * array gets a validation error, not a server error.
 */
class IndexQueryInputTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Book $book;

    private Act $act;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->book = Book::factory()->for($this->project)->create();
        $this->act = Act::factory()->for($this->book)->create(['name' => 'Act plain']);
        $this->chapter = Chapter::factory()->for($this->act)->create(['name' => 'Chapter plain']);
    }

    // ---------------------------------------------------------------------
    // LIKE wildcards
    // ---------------------------------------------------------------------

    public function test_the_scenes_search_matches_a_literal_percent_sign(): void
    {
        Scene::factory()->for($this->chapter)->create(['name' => 'Scene 100% done']);
        Scene::factory()->for($this->chapter)->create(['name' => 'Scene plain']);

        $this->actingAs($this->user)
            ->get(route('books.scenes.index', ['book' => $this->book, 'search' => '%']))
            ->assertOk()
            ->assertSee('Scene 100% done')
            ->assertDontSee('Scene plain');
    }

    public function test_the_scenes_search_matches_a_literal_underscore(): void
    {
        Scene::factory()->for($this->chapter)->create(['name' => 'snake_case']);
        Scene::factory()->for($this->chapter)->create(['name' => 'snakeXcase']);

        $this->actingAs($this->user)
            ->get(route('books.scenes.index', ['book' => $this->book, 'search' => 'e_c']))
            ->assertOk()
            ->assertSee('snake_case')
            ->assertDontSee('snakeXcase');
    }

    public function test_the_scenes_search_matches_the_escape_character_itself(): void
    {
        Scene::factory()->for($this->chapter)->create(['name' => 'Wow!%']);
        Scene::factory()->for($this->chapter)->create(['name' => 'Wow!X']);

        $this->actingAs($this->user)
            ->get(route('books.scenes.index', ['book' => $this->book, 'search' => '!%']))
            ->assertOk()
            ->assertSee('Wow!%')
            ->assertDontSee('Wow!X');
    }

    public function test_the_chapters_search_matches_a_literal_percent_sign(): void
    {
        Chapter::factory()->for($this->act)->create(['name' => 'Chapter 100% done']);

        $this->actingAs($this->user)
            ->get(route('books.chapters.index', ['book' => $this->book, 'search' => '%']))
            ->assertOk()
            ->assertSee('Chapter 100% done')
            ->assertDontSee('Chapter plain');
    }

    public function test_the_acts_search_matches_a_literal_percent_sign(): void
    {
        Act::factory()->for($this->book)->create(['name' => 'Act 100% done']);

        $this->actingAs($this->user)
            ->get(route('books.acts.index', ['book' => $this->book, 'search' => '%']))
            ->assertOk()
            ->assertSee('Act 100% done')
            ->assertDontSee('Act plain');
    }

    public function test_the_events_search_matches_a_literal_percent_sign(): void
    {
        Event::factory()->for($this->project)->create(['title' => 'Event 100% done']);
        Event::factory()->for($this->project)->create(['title' => 'Event plain']);

        $this->actingAs($this->user)
            ->get(route('projects.events.index', ['project' => $this->project, 'search' => '%']))
            ->assertOk()
            ->assertSee('Event 100% done')
            ->assertDontSee('Event plain');
    }

    public function test_the_plotlines_search_matches_a_literal_percent_sign(): void
    {
        Plotline::factory()->for($this->project)->create(['name' => 'Plotline 100% done']);
        Plotline::factory()->for($this->project)->create(['name' => 'Plotline plain']);

        $this->actingAs($this->user)
            ->get(route('projects.plotlines.index', ['project' => $this->project, 'search' => '%']))
            ->assertOk()
            ->assertSee('Plotline 100% done')
            ->assertDontSee('Plotline plain');
    }

    public function test_the_codex_search_matches_a_literal_percent_sign_in_names_and_aliases(): void
    {
        CodexEntry::factory()->for($this->project)->character()->create(['name' => 'Entry 100% done']);
        $aliased = CodexEntry::factory()->for($this->project)->character()->create(['name' => 'Entry with alias']);
        CodexAlias::factory()->for($aliased, 'entry')->create(['alias' => 'Alias 50% off']);
        CodexEntry::factory()->for($this->project)->character()->create(['name' => 'Entry plain']);

        $this->actingAs($this->user)
            ->get(route('projects.codex.index', [
                'project' => $this->project,
                'type' => CodexEntryType::Character->routeKey(),
                'search' => '%',
            ]))
            ->assertOk()
            ->assertSee('Entry 100% done')
            ->assertSee('Entry with alias')
            ->assertDontSee('Entry plain');
    }

    public function test_the_revision_label_filter_matches_a_literal_percent_sign(): void
    {
        $this->actRevision(['label' => 'Label 100% done']);
        $this->actRevision(['label' => 'Label plain']);

        $this->actingAs($this->user)
            ->get(route('revisions.index', ['entity' => 'act', 'id' => $this->act->id, 'label' => '%']))
            ->assertOk()
            ->assertSee('Label 100% done')
            ->assertDontSee('Label plain');
    }

    // ---------------------------------------------------------------------
    // Array query values
    // ---------------------------------------------------------------------

    public function test_the_scenes_index_rejects_array_filter_values(): void
    {
        foreach (['chapter', 'search', 'sort', 'direction'] as $key) {
            $this->actingAs($this->user)
                ->get(route('books.scenes.index', ['book' => $this->book, $key => ['1']]))
                ->assertRedirect()
                ->assertSessionHasErrors($key);
        }
    }

    public function test_the_chapters_index_rejects_array_filter_values(): void
    {
        foreach (['act', 'search'] as $key) {
            $this->actingAs($this->user)
                ->get(route('books.chapters.index', ['book' => $this->book, $key => ['1']]))
                ->assertRedirect()
                ->assertSessionHasErrors($key);
        }
    }

    public function test_the_acts_index_rejects_an_array_search(): void
    {
        $this->actingAs($this->user)
            ->get(route('books.acts.index', ['book' => $this->book, 'search' => ['x']]))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }

    public function test_the_events_index_rejects_array_filter_values(): void
    {
        foreach (['plotline', 'search'] as $key) {
            $this->actingAs($this->user)
                ->get(route('projects.events.index', ['project' => $this->project, $key => ['1']]))
                ->assertRedirect()
                ->assertSessionHasErrors($key);
        }
    }

    public function test_the_plotlines_index_rejects_an_array_search(): void
    {
        $this->actingAs($this->user)
            ->get(route('projects.plotlines.index', ['project' => $this->project, 'search' => ['x']]))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }

    public function test_the_codex_index_rejects_array_filter_values(): void
    {
        foreach (['tag', 'search'] as $key) {
            $this->actingAs($this->user)
                ->get(route('projects.codex.index', [
                    'project' => $this->project,
                    'type' => CodexEntryType::Character->routeKey(),
                    $key => ['1'],
                ]))
                ->assertRedirect()
                ->assertSessionHasErrors($key);
        }
    }

    public function test_the_story_overview_rejects_an_array_chapter(): void
    {
        $this->actingAs($this->user)
            ->get(route('books.story.overview', ['book' => $this->book, 'chapter' => [(string) $this->chapter->id]]))
            ->assertRedirect()
            ->assertSessionHasErrors('chapter');
    }

    public function test_the_revision_history_rejects_array_filter_values(): void
    {
        foreach (['label', 'field'] as $key) {
            $this->actingAs($this->user)
                ->get(route('revisions.index', ['entity' => 'act', 'id' => $this->act->id, $key => ['x']]))
                ->assertRedirect()
                ->assertSessionHasErrors($key);
        }
    }

    public function test_a_non_owner_still_gets_403_with_array_filter_values(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('books.scenes.index', ['book' => $this->book, 'chapter' => ['1']]))
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $overrides */
    private function actRevision(array $overrides): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Act::class,
            'revisionable_id' => $this->act->id,
            'project_id' => $this->project->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            ...$overrides,
        ]);
    }
}
