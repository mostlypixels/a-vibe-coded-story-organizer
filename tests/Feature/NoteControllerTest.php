<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->project = Project::factory()->for($this->owner)->create();
    }

    // --- Index -------------------------------------------------------------

    public function test_owner_sees_the_index_newest_update_first(): void
    {
        Note::factory()->for($this->project)->create(['title' => 'Older note', 'updated_at' => now()->subDay()]);
        Note::factory()->for($this->project)->create(['title' => 'Newer note', 'updated_at' => now()]);

        $this->actingAs($this->owner)->get(route('projects.notes.index', $this->project))
            ->assertOk()
            ->assertSeeInOrder(['Newer note', 'Older note']);
    }

    public function test_the_index_sorts_by_title_and_searches_by_title(): void
    {
        Note::factory()->for($this->project)->create(['title' => 'Zebra note']);
        Note::factory()->for($this->project)->create(['title' => 'Apple note']);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'sort' => 'title', 'direction' => 'asc']))
            ->assertSeeInOrder(['Apple note', 'Zebra note']);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'search' => 'Zebra']))
            ->assertSee('Zebra note')
            ->assertDontSee('Apple note');
    }

    public function test_the_linked_filter_keeps_notes_with_a_link_of_that_type(): void
    {
        $scene = $this->makeScene($this->makeBook());
        $codex = CodexEntry::factory()->for($this->project)->create();
        Note::factory()->for($this->project)->create(['title' => 'Scene note'])->linkTo($scene);
        Note::factory()->for($this->project)->create(['title' => 'Codex note'])->linkTo($codex);
        Note::factory()->for($this->project)->create(['title' => 'Loose note']);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'linked' => 'scene']))
            ->assertOk()
            ->assertSee('Scene note')
            ->assertDontSee('Codex note')
            ->assertDontSee('Loose note');
    }

    public function test_the_book_filter_matches_links_inside_the_book_only(): void
    {
        $bookA = $this->makeBook();
        $bookB = $this->makeBook();
        $sceneA = $this->makeScene($bookA);
        $sceneB = $this->makeScene($bookB);
        Note::factory()->for($this->project)->create(['title' => 'Note on scene A'])->linkTo($sceneA);
        Note::factory()->for($this->project)->create(['title' => 'Note on chapter A'])->linkTo($sceneA->chapter);
        Note::factory()->for($this->project)->create(['title' => 'Note on act A'])->linkTo($sceneA->chapter->act);
        Note::factory()->for($this->project)->create(['title' => 'Note on book A'])->linkTo($bookA);
        Note::factory()->for($this->project)->create(['title' => 'Note on scene B'])->linkTo($sceneB);
        Note::factory()->for($this->project)->create(['title' => 'Note on book B'])->linkTo($bookB);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'book' => $bookA->id]))
            ->assertOk()
            ->assertSee('Note on scene A')
            ->assertSee('Note on chapter A')
            ->assertSee('Note on act A')
            ->assertSee('Note on book A')
            ->assertDontSee('Note on scene B')
            ->assertDontSee('Note on book B');
    }

    public function test_the_filters_combine_with_the_category_and_search(): void
    {
        $category = NoteCategory::factory()->for($this->project)->create();
        $scene = $this->makeScene($this->makeBook());
        Note::factory()->for($this->project)->create(['title' => 'Match note', 'note_category_id' => $category->id])->linkTo($scene);
        Note::factory()->for($this->project)->create(['title' => 'Other category note'])->linkTo($scene);
        Note::factory()->for($this->project)->create(['title' => 'Match unlinked', 'note_category_id' => $category->id]);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', [
                'project' => $this->project, 'category' => $category->id, 'linked' => 'scene', 'search' => 'Match',
            ]))
            ->assertOk()
            ->assertSee('Match note')
            ->assertDontSee('Other category note')
            ->assertDontSee('Match unlinked');
    }

    public function test_the_index_shows_the_link_count_and_sorts_by_update_date(): void
    {
        $scene = $this->makeScene($this->makeBook());
        $note = Note::factory()->for($this->project)->create(['title' => 'Old note', 'updated_at' => now()->subDay()]);
        $note->linkTo($scene);
        $note->linkTo($scene->chapter);
        Note::factory()->for($this->project)->create(['title' => 'New note', 'updated_at' => now()]);

        $response = $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'sort' => 'updated_at', 'direction' => 'asc']));

        $response->assertOk()->assertSeeInOrder(['Old note', 'New note']);
        $this->assertSame(2, $response->viewData('notes')->firstWhere('id', $note->id)->links_count);
    }

    public function test_the_book_select_shows_only_with_more_than_one_book(): void
    {
        $this->assertSame(1, $this->project->books()->count());

        $this->actingAs($this->owner)->get(route('projects.notes.index', $this->project))
            ->assertOk()
            ->assertDontSee('name="book"', false);

        $this->makeBook();

        $this->actingAs($this->owner)->get(route('projects.notes.index', $this->project))
            ->assertOk()
            ->assertSee('name="book"', false);
    }

    public function test_invalid_filter_values_get_a_validation_error(): void
    {
        $foreignBook = Book::factory()->for(Project::factory()->for($this->owner)->create())->create();

        foreach ([
            ['linked' => 'nonsense'],
            ['linked' => ['scene']],
            ['book' => 'abc'],
            ['book' => $foreignBook->id],
            ['search' => ['x']],
        ] as $query) {
            $key = array_key_first($query);

            $this->actingAs($this->owner)
                ->get(route('projects.notes.index', ['project' => $this->project, ...$query]))
                ->assertSessionHasErrors($key);
        }
    }

    public function test_the_filters_do_not_open_the_index_to_a_non_owner(): void
    {
        $this->actingAs($this->other)
            ->get(route('projects.notes.index', ['project' => $this->project, 'linked' => 'scene']))
            ->assertForbidden();
    }

    private function makeBook(): Book
    {
        return Book::factory()->for($this->project)->create();
    }

    private function makeScene(Book $book): Scene
    {
        return Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($book)))->create();
    }

    public function test_the_index_lists_only_this_projects_notes(): void
    {
        $elsewhere = Project::factory()->for($this->owner)->create();
        Note::factory()->for($elsewhere)->create(['title' => 'Foreign note']);

        $this->actingAs($this->owner)->get(route('projects.notes.index', $this->project))
            ->assertOk()
            ->assertDontSee('Foreign note');
    }

    public function test_the_notes_menu_link_is_current_on_note_pages(): void
    {
        $note = Note::factory()->for($this->project)->create();

        $html = $this->actingAs($this->owner)->get(route('notes.show', $note))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(e(route('projects.notes.index', $this->project)), '/').'"[^>]*aria-current="page"/',
            $html,
        );
    }

    public function test_the_show_page_title_and_trail_name_the_note(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Trail note']);

        $this->actingAs($this->owner)->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee('<title>Trail note - ', false)
            ->assertSee(__('Notes'));
    }

    public function test_the_contents_card_shows_at_three_headings_and_links_to_anchors(): void
    {
        $note = Note::factory()->for($this->project)->create([
            'body' => '<h2>Tides</h2><p>a</p><h3>Moon</h3><h2>Tides</h2>',
        ]);

        $this->actingAs($this->owner)->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee('aria-label="'.__('Contents').'"', false)
            ->assertSee('href="#tides"', false)
            ->assertSee('href="#tides-2"', false)
            ->assertSee('href="#moon"', false)
            ->assertSee('<h2 id="tides-2">Tides</h2>', false);
    }

    public function test_the_contents_card_is_hidden_under_three_headings(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => '<h2>One</h2><h2>Two</h2>']);

        $this->actingAs($this->owner)->get(route('notes.show', $note))
            ->assertOk()
            ->assertDontSee('aria-label="'.__('Contents').'"', false)
            ->assertSee('<h2 id="one">One</h2>', false);
    }

    public function test_viewing_a_note_leaves_the_stored_body_unchanged(): void
    {
        $body = '<h2>One</h2><h2>Two</h2><h2>Three</h2>';
        $note = Note::factory()->for($this->project)->create(['body' => $body]);
        $stored = $note->fresh()->body;

        $this->actingAs($this->owner)->get(route('notes.show', $note))->assertOk();

        $this->assertSame($stored, $note->fresh()->body);
        $this->assertStringNotContainsString(' id=', $note->fresh()->body);
    }

    // --- Create and store ----------------------------------------------------

    public function test_owner_can_open_the_create_form(): void
    {
        $this->actingAs($this->owner)->get(route('projects.notes.create', $this->project))
            ->assertOk()
            ->assertSee('name="title"', false);
    }

    public function test_owner_can_store_a_note(): void
    {
        $response = $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), [
            'title' => 'Research on tides',
            'body' => '<p>High at noon.</p>',
        ]);

        $note = Note::firstOrFail();
        $response->assertRedirect(route('notes.show', $note));
        $this->assertSame($this->project->id, $note->project_id);
        $this->assertSame('Research on tides', $note->title);
        $this->assertSame('<p>High at noon.</p>', $note->body);
    }

    public function test_the_body_is_optional_and_sanitized(): void
    {
        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), ['title' => 'Bare'])
            ->assertSessionHasNoErrors();
        $this->assertNull(Note::where('title', 'Bare')->firstOrFail()->body);

        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), [
            'title' => 'Dirty',
            'body' => '<p>Kept</p><script>bad()</script>',
        ]);
        $this->assertStringNotContainsString('script', Note::where('title', 'Dirty')->firstOrFail()->body);
    }

    public function test_title_is_required_and_capped_at_255_on_store(): void
    {
        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), ['title' => ''])
            ->assertSessionHasErrors('title');
        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), ['title' => str_repeat('a', 256)])
            ->assertSessionHasErrors('title');
        $this->assertSame(0, Note::count());
    }

    public function test_the_body_is_capped_by_the_registry_on_store(): void
    {
        $cap = AutosavableFields::characterCap('note', 'body');

        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), [
            'title' => 'Too long',
            'body' => str_repeat('a', $cap + 1),
        ])->assertSessionHasErrors('body');
        $this->assertSame(0, Note::count());
    }

    // --- Show, edit, update --------------------------------------------------

    public function test_create_with_a_link_key_stores_the_link(): void
    {
        $scene = Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($this->project->books()->first())))->create(['name' => 'The fountain']);

        $this->actingAs($this->owner)->get(route('projects.notes.create', ['project' => $this->project, 'link' => 'scene:'.$scene->id]))
            ->assertOk()
            ->assertSee('The fountain')
            ->assertSee('value="scene:'.$scene->id.'"', false);

        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), ['title' => 'Fountain notes', 'link' => 'scene:'.$scene->id])
            ->assertSessionHasNoErrors();

        $note = Note::query()->where('title', 'Fountain notes')->firstOrFail();
        $this->assertTrue($note->scenes()->whereKey($scene->id)->exists());
    }

    public function test_create_rejects_a_foreign_or_malformed_link_key(): void
    {
        $foreignBook = Project::factory()->for($this->other)->create()->books()->first();
        $foreign = Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($foreignBook)))->create(['name' => 'Elsewhere']);

        $this->actingAs($this->owner)->get(route('projects.notes.create', ['project' => $this->project, 'link' => 'scene:'.$foreign->id]))
            ->assertOk()
            ->assertDontSee('Elsewhere');

        foreach (['scene:'.$foreign->id, 'tag:1', 'scene'] as $link) {
            $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), ['title' => 'Stray', 'link' => $link])
                ->assertSessionHasErrors('link');
        }

        $this->assertDatabaseMissing('notes', ['title' => 'Stray']);
    }

    public function test_owner_can_view_a_note(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Readable', 'body' => '<p>Body text</p>']);

        $this->actingAs($this->owner)->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee('Readable')
            ->assertSee('Body text');
    }

    public function test_the_edit_page_autosaves_the_body_and_links_history(): void
    {
        $note = Note::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->get(route('notes.edit', $note))
            ->assertOk()
            ->assertSee('data-autosave-field="note:'.$note->id.':body"', false)
            ->assertSee(route('revisions.index', ['entity' => 'note', 'id' => $note->id]), false);
    }

    public function test_owner_can_update_a_note(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Before', 'body' => '<p>Old</p>']);

        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'After',
            'body' => '<p>New</p>',
            'base_hashes' => ['body' => hash('sha256', '<p>Old</p>')],
        ])->assertRedirect(route('projects.notes.index', $this->project));

        $note->refresh();
        $this->assertSame('After', $note->title);
        $this->assertSame('<p>New</p>', $note->body);
    }

    public function test_save_and_stay_returns_to_the_edit_page(): void
    {
        $note = Note::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'Stay',
            'body' => $note->body,
            'stay' => '1',
        ])->assertRedirect(route('notes.edit', $note));
    }

    public function test_title_is_required_on_update(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Keep']);

        $this->actingAs($this->owner)->put(route('notes.update', $note), ['title' => '', 'body' => $note->body])
            ->assertSessionHasErrors('title');
        $this->assertSame('Keep', $note->fresh()->title);
    }

    public function test_the_body_is_capped_by_the_registry_on_update(): void
    {
        $note = Note::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'Too long',
            'body' => str_repeat('a', AutosavableFields::characterCap('note', 'body') + 1),
        ])->assertSessionHasErrors('body');
    }

    // --- Destroy ---------------------------------------------------------------

    public function test_owner_can_delete_a_note(): void
    {
        $note = Note::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->delete(route('notes.destroy', $note))
            ->assertRedirect(route('projects.notes.index', $this->project));

        $this->assertModelMissing($note);
    }

    // --- Autosave and History ---------------------------------------------------

    public function test_autosave_records_a_revision_and_the_history_page_loads(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => '<p>One</p>']);

        $this->actingAs($this->owner)->patchJson(
            route('autosave.update', ['entity' => 'note', 'id' => $note->id, 'field' => 'body']),
            ['value' => '<p>Two</p>', 'base_hash' => hash('sha256', '<p>One</p>')],
        )->assertOk();

        $this->assertSame('<p>Two</p>', $note->fresh()->body);
        $this->assertTrue(Revision::where('revisionable_id', $note->id)->where('field', 'body')->exists());

        $this->actingAs($this->owner)->get(route('revisions.index', ['entity' => 'note', 'id' => $note->id]))
            ->assertOk();
    }

    public function test_autosave_refuses_a_body_over_the_cap(): void
    {
        $note = Note::factory()->for($this->project)->create(['body' => null]);

        $this->actingAs($this->owner)->patchJson(
            route('autosave.update', ['entity' => 'note', 'id' => $note->id, 'field' => 'body']),
            ['value' => str_repeat('a', 500_001), 'base_hash' => hash('sha256', '')],
        )->assertStatus(422);
    }

    // --- Authorization ------------------------------------------------------------

    public function test_a_non_owner_gets_403_on_every_action(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Private']);
        $as = $this->actingAs($this->other);

        $as->get(route('projects.notes.index', $this->project))->assertForbidden();
        $as->get(route('projects.notes.create', $this->project))->assertForbidden();
        $as->post(route('projects.notes.store', $this->project), ['title' => 'X'])->assertForbidden();
        $as->get(route('notes.show', $note))->assertForbidden();
        $as->get(route('notes.edit', $note))->assertForbidden();
        $as->put(route('notes.update', $note), ['title' => 'Hacked'])->assertForbidden();
        $as->delete(route('notes.destroy', $note))->assertForbidden();
        $as->get(route('revisions.index', ['entity' => 'note', 'id' => $note->id]))->assertForbidden();
        $as->patchJson(
            route('autosave.update', ['entity' => 'note', 'id' => $note->id, 'field' => 'body']),
            ['value' => '<p>x</p>', 'base_hash' => hash('sha256', (string) $note->body)],
        )->assertForbidden();

        $this->assertSame(1, Note::count());
        $this->assertSame('Private', $note->fresh()->title);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('projects.notes.index', $this->project))->assertRedirect(route('login'));
    }
}
