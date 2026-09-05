<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a full project -> act -> chapter chain owned by the given user and
     * return the leaf chapter (scenes hang off chapters).
     */
    private function chapterFor(User $user): Chapter
    {
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        return Chapter::factory()->for($act)->create();
    }

    private function validPayload(Chapter $chapter, array $overrides = []): array
    {
        return array_merge([
            'chapter_id' => $chapter->id,
            'name' => 'Opening scene',
            'description' => 'A test scene',
            'contents' => 'Some **markdown** contents.',
            'notes' => null,
            'status' => SceneStatus::Draft->value,
        ], $overrides);
    }

    public function test_the_scenes_index_lists_scenes_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['name' => 'A memorable scene']);

        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->get(route('books.scenes.index', $book))
            ->assertOk()
            ->assertSee('A memorable scene');
    }

    public function test_the_scenes_index_shows_only_the_scenes_of_the_book_in_the_url(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        $firstChapter = Chapter::factory()->for(Act::factory()->for($firstBook))->create();
        $secondChapter = Chapter::factory()->for(Act::factory()->for($secondBook))->create();
        Scene::factory()->for($firstChapter)->create(['name' => 'Volume one scene']);
        Scene::factory()->for($secondChapter)->create(['name' => 'Volume two scene']);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $firstBook))
            ->assertOk()
            ->assertSee('Volume one scene')
            ->assertDontSee('Volume two scene');
    }

    public function test_a_scene_cannot_be_attached_to_a_chapter_from_another_book(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $foreignChapter = Chapter::factory()->for(Act::factory()->for(Book::factory()->for($project)))->create();

        $this->actingAs($user)
            ->post(route('books.scenes.store', $firstBook), $this->validPayload($foreignChapter))
            ->assertSessionHasErrors('chapter_id');

        $this->assertSame(0, Scene::count());
    }

    public function test_a_user_cannot_view_scenes_of_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);

        $this->actingAs($other)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertForbidden();
    }

    public function test_the_scenes_index_footer_totals_words_across_the_listed_scenes(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['contents' => trim(str_repeat('word ', 613))]);
        Scene::factory()->for($chapter)->create(['contents' => trim(str_repeat('word ', 449))]);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->assertSee('Total')
            ->assertSee('1,062 words'); // sum, distinct from either scene's own count
    }

    public function test_a_user_can_create_a_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $response = $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter));

        $response->assertRedirect(route('books.scenes.index', $book));

        $scene = Scene::first();
        $this->assertNotNull($scene);
        $this->assertSame('Opening scene', $scene->name);
        $this->assertSame($chapter->id, $scene->chapter_id);
        $this->assertSame(SceneStatus::Draft, $scene->status);
    }

    public function test_scene_positions_are_auto_assigned_sequentially_within_a_chapter(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['name' => 'First']));
        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['name' => 'Second']));

        $this->assertSame(1, Scene::where('name', 'First')->value('position'));
        $this->assertSame(2, Scene::where('name', 'Second')->value('position'));
    }

    public function test_a_user_cannot_create_a_scene_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($other)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter))
            ->assertForbidden();

        $this->assertSame(0, Scene::count());
    }

    public function test_scene_creation_requires_a_name(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_scene_creation_requires_a_valid_status(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['status' => 'not-a-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_a_scene_cannot_be_attached_to_a_chapter_from_another_project(): void
    {
        $user = User::factory()->create();
        [$ownProject, $ownBook] = $this->projectWithBook($user);
        $foreignChapter = $this->chapterFor($user); // belongs to a different project

        // Posting to $ownProject with a chapter_id that lives outside it must fail validation.
        $this->actingAs($user)
            ->post(route('books.scenes.store', $ownBook), $this->validPayload($foreignChapter))
            ->assertSessionHasErrors('chapter_id');

        $this->assertSame(0, Scene::count());
    }

    public function test_a_user_can_update_a_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Old name']);
        $book = $chapter->act->book;
        $project = $book->project;

        $response = $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['name' => 'New name', 'status' => SceneStatus::Final->value]),
        );

        $response->assertRedirect(route('books.scenes.index', $book));

        $scene = $scene->fresh();
        $this->assertSame('New name', $scene->name);
        $this->assertSame(SceneStatus::Final, $scene->status);
    }

    public function test_saving_the_edit_form_records_a_labeled_manual_revision_for_the_changed_contents(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Old contents.']);

        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'Some **markdown** contents.']),
        );

        $revision = $scene->revisions()->where('field', 'contents')->latest('created_at')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionOrigin::Manual, $revision->origin);
        $this->assertNotNull($revision->label);
    }

    /**
     * Same rule as the autosave path: the baseline the form save seeds is the
     * text the writer started from, never the text the form just wrote.
     */
    public function test_saving_the_edit_form_seeds_a_baseline_holding_the_pre_edit_contents(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Old contents.']);

        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'Some **markdown** contents.']),
        );

        $baseline = $scene->revisions()->where('field', 'contents')->where('origin', RevisionOrigin::Baseline)->sole();

        $this->assertSame('Old contents.', $baseline->value);
    }

    public function test_a_user_cannot_update_another_users_scene(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Untouched']);

        $this->actingAs($other)
            ->put(route('scenes.update', $scene), $this->validPayload($chapter, ['name' => 'Hacked']))
            ->assertForbidden();

        $this->assertSame('Untouched', $scene->fresh()->name);
    }

    public function test_a_user_can_delete_a_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create();
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->delete(route('scenes.destroy', $scene))
            ->assertRedirect(route('books.scenes.index', $book));

        $this->assertNull($scene->fresh());
    }

    public function test_a_user_cannot_delete_another_users_scene(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($other)
            ->delete(route('scenes.destroy', $scene))
            ->assertForbidden();

        $this->assertNotNull($scene->fresh());
    }

    public function test_a_scene_can_be_created_without_an_event(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter))
            ->assertRedirect(route('books.scenes.index', $book));

        $this->assertNull(Scene::first()->event_id);
    }

    public function test_a_scene_can_happen_during_an_existing_event(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $event = Event::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['event_id' => $event->id]));

        $this->assertSame($event->id, Scene::first()->event_id);
    }

    public function test_the_edit_page_names_the_inline_event_fields_to_match_the_controller(): void
    {
        // The picker name is `event_id`, but the inline-new fields must post
        // `new_event_title`/`new_event_datetime` — the keys the controller reads.
        // A stray `_id` in those names drops the typed event with no error.
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('name="new_event_title"', false)
            ->assertSee('name="new_event_datetime"', false)
            ->assertDontSee('name="new_event_id_title"', false);
    }

    public function test_the_inline_new_event_form_creates_an_event_attached_to_the_main_plotline(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;

        $this->actingAs($user)->post(route('books.scenes.store', $book), $this->validPayload($chapter, [
            'new_event_title' => 'A brand new event',
            'new_event_datetime' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]));

        $event = Event::where('title', 'A brand new event')->first();
        $this->assertNotNull($event);
        $this->assertSame($event->id, Scene::first()->event_id);

        $mainPlotline = $project->plotlines()->where('is_main', true)->first();
        $this->assertTrue($event->plotlines->contains($mainPlotline));
    }

    public function test_a_scene_cannot_happen_during_an_event_from_another_project(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $foreignEvent = Event::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['event_id' => $foreignEvent->id]))
            ->assertSessionHasErrors('event_id');
    }

    public function test_a_scene_can_mention_multiple_events(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $first = Event::factory()->for($project)->create();
        $second = Event::factory()->for($project)->create();

        $this->actingAs($user)->post(
            route('books.scenes.store', $book),
            $this->validPayload($chapter, ['mentioned_events' => [$first->id, $second->id]]),
        );

        $scene = Scene::first();
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $scene->mentionedEvents->pluck('id')->all(),
        );
    }

    public function test_mentioned_events_from_another_project_are_rejected(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $foreignEvent = Event::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->post(route('books.scenes.store', $book), $this->validPayload($chapter, ['mentioned_events' => [$foreignEvent->id]]))
            ->assertSessionHasErrors('mentioned_events.0');

        $this->assertSame(0, Scene::count());
    }

    public function test_deleting_an_event_unassigns_scenes_and_clears_mentions(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;
        $event = Event::factory()->for($project)->create();

        $happensDuring = Scene::factory()->for($chapter)->create(['event_id' => $event->id]);
        $mentions = Scene::factory()->for($chapter)->create();
        $mentions->mentionedEvents()->attach($event);

        $this->actingAs($user)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('projects.events.index', $project));

        $this->assertNull($happensDuring->fresh()->event_id);
        $this->assertCount(0, $mentions->fresh()->mentionedEvents);
    }

    public function test_the_scenes_index_flags_unassigned_scenes(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['event_id' => null]);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->assertSee('Unassigned');
    }

    public function test_the_scene_form_renders_the_event_controls(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->get(route('books.scenes.create', $book))
            ->assertOk()
            ->assertSee('Happens during')
            ->assertSee('Mentions events')
            ->assertSee('Search events by name or date', false);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Happens during');
    }

    public function test_the_story_overview_shows_the_happens_during_event(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $event = Event::factory()->for($project)->create(['title' => 'The Coronation']);
        Scene::factory()->for($chapter)->create(['event_id' => $event->id]);
        Scene::factory()->for($chapter)->create(['event_id' => null]);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('The Coronation')
            ->assertSee('Unassigned');
    }

    // ---------------------------------------------------------------------
    // Reordering — the swap logic extracted to HasSiblingPosition
    // ---------------------------------------------------------------------

    public function test_move_down_swaps_position_with_the_next_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        $second = Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('scenes.move-down', $first))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_move_up_swaps_position_with_the_previous_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        $second = Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($user)
            ->patch(route('scenes.move-up', $second))
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_move_down_at_the_end_of_the_chapter_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        $last = Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($user)->patch(route('scenes.move-down', $last))->assertRedirect();

        // Nothing to swap with — positions are untouched.
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $last->fresh()->position);
    }

    public function test_scenes_only_swap_with_siblings_in_the_same_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapterOne = Chapter::factory()->for($act)->create();
        $chapterTwo = Chapter::factory()->for($act)->create();

        $sceneOne = Scene::factory()->for($chapterOne)->create(['position' => 1]);
        $sceneTwo = Scene::factory()->for($chapterTwo)->create(['position' => 2]);

        // Moving the first chapter's only scene down finds no sibling to swap with,
        // so the scene in the OTHER chapter is never touched (scope column matters).
        $this->actingAs($user)->patch(route('scenes.move-down', $sceneOne))->assertRedirect();

        $this->assertSame(1, $sceneOne->fresh()->position);
        $this->assertSame(2, $sceneTwo->fresh()->position);
    }

    public function test_move_returns_the_new_position_as_json_when_requested(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($user)
            ->patchJson(route('scenes.move-down', $first))
            ->assertOk()
            ->assertJson(['position' => 2]);
    }

    public function test_a_user_cannot_reorder_another_users_scene(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $scene = Scene::factory()->for($chapter)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($other)
            ->patch(route('scenes.move-down', $scene))
            ->assertForbidden();

        $this->assertSame(1, $scene->fresh()->position);
    }

    public function test_rendered_contents_accessor_renders_markdown_to_html(): void
    {
        $scene = new Scene(['contents' => 'Prose with **bold** words.']);

        $this->assertStringContainsString('<strong>bold</strong>', $scene->renderedContents);
    }

    public function test_rendered_contents_accessor_renders_strikethrough_as_s(): void
    {
        $scene = new Scene(['contents' => '~~Dear~~ friend']);

        // `<del>` is reserved for revision diffs, so the sanitizer strips it.
        $this->assertStringContainsString('<s>Dear</s>', $scene->renderedContents);
        $this->assertStringNotContainsString('<del>', $scene->renderedContents);
    }

    public function test_rendered_contents_accessor_is_empty_for_null_contents(): void
    {
        $scene = new Scene(['contents' => null]);

        $this->assertSame('', trim($scene->renderedContents));
    }

    // ---------------------------------------------------------------------
    // Codex reference matching on save (SceneReferenceMatcher wiring)
    // ---------------------------------------------------------------------

    /**
     * Seed a codex entry named `$name` (with an optional alias) in the given project.
     */
    private function codexEntryIn(Project $project, string $name, ?string $alias = null): CodexEntry
    {
        $entry = CodexEntry::factory()->for($project)->create(['name' => $name]);

        if ($alias !== null) {
            CodexAlias::factory()->for($entry, 'entry')->create(['alias' => $alias]);
        }

        return $entry;
    }

    public function test_creating_a_scene_that_mentions_a_codex_alias_links_the_entry(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');

        $this->actingAs($user)->post(
            route('books.scenes.store', $book),
            $this->validPayload($chapter, ['contents' => 'Mel walked into the room.']),
        )->assertRedirect(route('books.scenes.index', $book));

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => Scene::first()->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_updating_a_scene_adds_then_removes_the_reference_row(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Nobody here.']);

        // Update to add mentioning text → row created.
        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'Then Mel arrived.']),
        )->assertRedirect(route('books.scenes.index', $book));

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);

        // Update again to remove the mention → the stale row is dropped (full resync).
        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'The room is empty again.']),
        )->assertRedirect(route('books.scenes.index', $book));

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_saving_a_scene_with_no_matches_leaves_no_reference_rows(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $this->codexEntryIn($project, 'Melchior', 'Mel');

        $this->actingAs($user)->post(
            route('books.scenes.store', $book),
            $this->validPayload($chapter, ['contents' => 'Just an ordinary melody, nothing more.']),
        )->assertRedirect(route('books.scenes.index', $book));

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => Scene::first()->id,
        ]);
    }

    public function test_a_non_owner_cannot_update_a_scene_and_no_references_are_recomputed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $project = $chapter->act->book->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Untouched.']);

        $this->actingAs($other)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'Mel is mentioned here.']),
        )->assertForbidden();

        $this->assertSame('Untouched.', $scene->fresh()->contents);
        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    // --- "Codex references" sidebar on the scene edit page ---------------

    public function test_the_edit_page_lists_referenced_codex_entries_linking_to_their_show_pages(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;
        $scene = Scene::factory()->for($chapter)->create();
        $entry = $this->codexEntryIn($project, 'Melchior');

        // Seed the pivot directly — this read-path UI renders whatever the matcher last wrote.
        $scene->codexReferences()->attach($entry);

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertOk()
            ->assertSee('Codex references')
            ->assertSee('Melchior')
            ->assertSee($entry->type->label())
            ->assertSee(route('codex.show', $entry), escape: false);
    }

    public function test_the_edit_page_shows_the_last_save_caption(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Detected from the scene contents on last save.');
    }

    /**
     * The `beforeunload` unsaved-changes prompt is suppressed for the Save / Save and stay
     * submit by `resources/js/navigation-guard.js`, which reads `data-guard-save` off the
     * submit event's `submitter`. The JS predicate is unit-tested in
     * `resources/js/navigation-guard.test.js`; this covers the other half of the wiring —
     * that `<x-edit-actions>` really emits the attribute on both buttons, so a refactor of
     * the shared component (or of `<x-button>`'s attribute merging) can't silently bring
     * the spurious prompt back.
     */
    public function test_the_edit_page_marks_both_save_buttons_for_the_navigation_guard(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene))->assertOk();

        // Blade renders a valueless component attribute in its boolean form,
        // `data-guard-save="data-guard-save"` — one per Save button. The JS only needs the
        // attribute to be *present* (`[data-guard-save]`), so any value would do; asserting
        // the exact rendered form just keeps this count unambiguous.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'data-guard-save="data-guard-save"'),
            'Both Save and Save and stay must carry data-guard-save.'
        );
    }

    public function test_the_edit_page_shows_the_empty_state_when_there_are_no_references(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('No codex entries referenced yet.');
    }

    public function test_a_non_owner_cannot_view_the_scene_edit_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($owner))->create();

        $this->actingAs($other)->get(route('scenes.edit', $scene))->assertForbidden();
    }

    public function test_the_public_share_page_never_exposes_codex_references(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;
        $scene = Scene::factory()->for($chapter)->create();
        $scene->forceFill([
            'share_token' => 'live-token',
            'share_expires_at' => now()->addDay(),
        ])->save();
        $entry = $this->codexEntryIn($project, 'SecretCodexEntity');
        $scene->codexReferences()->attach($entry);

        $this->get(route('shared.scenes.show', 'live-token'))
            ->assertOk()
            ->assertDontSee('SecretCodexEntity')
            ->assertDontSee('Codex references');
    }

    public function test_the_edit_page_links_to_the_scenes_revision_history(): void
    {
        // The Actions card carries the entity-level History link. The closing
        // quote prevents a match on the per-field `?field=` icon links beside
        // the scene's three autosaved fields.
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee(
                'href="'.route('revisions.index', ['entity' => 'scene', 'id' => $scene->id]).'"',
                false,
            );
    }

    // --- Index ordering by story order -----------------------------------

    /**
     * Two acts with a chapter and a scene each, then act B is moved above act A.
     * The `#` column must follow the story, so B's scene comes first — the previous
     * `orderBy('chapter_id')` grouping kept A first forever, because chapter_id only
     * matches story order until something is reordered.
     */
    public function test_the_scenes_index_orders_by_story_order_after_an_act_is_reordered(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $actA = Act::factory()->for($book)->create(['position' => 1]);
        $actB = Act::factory()->for($book)->create(['position' => 2]);
        $chapterA = Chapter::factory()->for($actA)->create(['position' => 1]);
        $chapterB = Chapter::factory()->for($actB)->create(['position' => 1]);
        Scene::factory()->for($chapterA)->create(['name' => 'Scene from A', 'position' => 1]);
        Scene::factory()->for($chapterB)->create(['name' => 'Scene from B', 'position' => 1]);

        $this->actingAs($user)->patch(route('acts.move-up', $actB));

        $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'sort' => 'position']))
            ->assertOk()
            ->assertSeeInOrder(['Scene from B', 'Scene from A']);
    }

    /**
     * Descending must reverse *every* ordering key, so the list reads as the story
     * backwards — not chapters ascending with their scenes reversed inside them.
     */
    public function test_the_scenes_index_reverses_the_whole_story_when_sorted_descending(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['position' => 1]);
        $first = Chapter::factory()->for($act)->create(['position' => 1]);
        $second = Chapter::factory()->for($act)->create(['position' => 2]);
        Scene::factory()->for($first)->create(['name' => 'Scene One', 'position' => 1]);
        Scene::factory()->for($first)->create(['name' => 'Scene Two', 'position' => 2]);
        Scene::factory()->for($second)->create(['name' => 'Scene Three', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'sort' => 'position', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Scene Three', 'Scene Two', 'Scene One']);
    }

    /**
     * `chapters` and `acts` both carry `name` and `position` columns of their own, so
     * the joins added for story order make an unqualified `name` ambiguous. This
     * covers both places it appears: the search filter and `?sort=name`.
     */
    public function test_the_scenes_index_still_sorts_and_searches_by_name(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        Scene::factory()->for($chapter)->create(['name' => 'Zebra', 'position' => 1]);
        Scene::factory()->for($chapter)->create(['name' => 'Antelope', 'position' => 2]);

        $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'sort' => 'name']))
            ->assertOk()
            ->assertSeeInOrder(['Antelope', 'Zebra']);

        $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'search' => 'Zeb']))
            ->assertOk()
            ->assertSee('Zebra')
            ->assertDontSee('Antelope');
    }

    /**
     * The joins must not leak their columns onto the hydrated scenes: `chapters` and
     * `acts` have `name`, `position` and `id` of their own, and without an explicit
     * `select('scenes.*')` they would overwrite the scene's.
     */
    public function test_the_scenes_index_hydrates_scene_columns_not_the_joined_ones(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'The scene name', 'position' => 1]);

        $scenes = $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->viewData('scenes');

        $this->assertSame($scene->id, $scenes->first()->id);
        $this->assertSame('The scene name', $scenes->first()->name);
    }

    // --- Continuous numbering --------------------------------------------

    /**
     * The trimmed, tag-stripped text of the `$index`-th `<td>` (0-based) in the row
     * whose name is `$rowName` — scoped to a single `<tr>...</tr>` block so it
     * can't be fooled by an id or word count elsewhere on the page matching.
     */
    private function columnCellFor(string $html, string $rowName, int $index): string
    {
        preg_match('/<tr[^>]*>((?:(?!<\/tr>).)*?'.preg_quote($rowName, '/').'(?:(?!<\/tr>).)*?)<\/tr>/s', $html, $rowMatch);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $rowMatch[1] ?? '', $cellMatches);

        return isset($cellMatches[1][$index]) ? trim(strip_tags($cellMatches[1][$index])) : '';
    }

    /** The '#' column (index 0) is the same across every scenes-index row. */
    private function numberColumnFor(string $html, string $rowName): string
    {
        return $this->columnCellFor($html, $rowName, 0);
    }

    public function test_the_scenes_index_number_column_is_continuous_not_the_per_chapter_position(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $otherChapter = Chapter::factory()->for($chapter->act)->create(['position' => $chapter->position + 1]);
        Scene::factory()->for($chapter)->create(['name' => 'Opening', 'position' => 1]);
        // Gappy per-chapter position (5): a regression back to `$scene->position`
        // would render this row's '#' cell as "5" instead of "2".
        Scene::factory()->for($otherChapter)->create(['name' => 'Closing', 'position' => 5]);

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'sort' => 'position']))
            ->assertOk()
            ->getContent();

        $this->assertSame('2', $this->numberColumnFor($html, 'Closing'));
    }

    /**
     * The new "In chapter" column shows the raw, per-chapter `position` — the
     * gappy sibling-order value move up/down writes — which is deliberately not
     * the same number as the continuous '#' column beside it.
     */
    public function test_the_scenes_index_shows_the_in_chapter_column_with_the_raw_position(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['name' => 'A gappy scene', 'position' => 7]);

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->getContent();

        // Column order: #, Title, Chapter, In chapter, Status, Event, Words, actions.
        $this->assertSame('7', $this->columnCellFor($html, 'A gappy scene', 3));
        // The '#' column beside it shows the continuous number (1, the only scene
        // in the project), deliberately not the same value as the raw position.
        $this->assertSame('1', $this->numberColumnFor($html, 'A gappy scene'));
    }

    /**
     * Filtering the list to one chapter must never renumber it: the map is built
     * from the whole project, so the first (and only) row shown still reads its
     * true, book-wide number.
     */
    public function test_the_scenes_index_numbers_stay_book_wide_when_filtered_to_one_chapter(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $book = $chapter->act->book;
        $project = $book->project;
        $otherChapter = Chapter::factory()->for($chapter->act)->create(['position' => $chapter->position + 1]);
        Scene::factory()->for($chapter)->create(['position' => 1]);
        Scene::factory()->for($chapter)->create(['position' => 2]);
        Scene::factory()->for($otherChapter)->create(['name' => 'Fresh Start', 'position' => 1]);

        $html = $this->actingAs($user)
            ->get(route('books.scenes.index', ['book' => $book, 'chapter' => $otherChapter->id, 'sort' => 'position']))
            ->assertOk()
            ->getContent();

        $this->assertSame('3', $this->numberColumnFor($html, 'Fresh Start'));
    }

    /**
     * The edit page's position hint shows both the continuous, book-wide number
     * and the scene's rank among its chapter's siblings — the latter a gap-free
     * rank, not the raw (possibly gappy) `position` column.
     */
    public function test_the_edit_page_shows_the_continuous_number_and_position_within_the_chapter(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['position' => 1]);
        $scene = Scene::factory()->for($chapter)->create(['position' => 2]);
        Scene::factory()->for($chapter)->create(['position' => 3]);

        // Continuous number 2 (2nd scene overall, the only chapter in the project);
        // rank 2 of 3 within its chapter, which is itself chapter number 1.
        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Scene 2 — 2 of 3 in Chapter 1. Use the move up/down buttons on the list to reorder.');
    }

    // --- Word count column -----------------------------------------------

    public function test_the_scenes_index_shows_each_scenes_word_count(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        // A total far outside the range a position or id on this row could
        // produce, so the assertion can only be satisfied by scenes.word_count.
        Scene::factory()->for($chapter)->create([
            'name' => 'A wordy scene',
            'contents' => trim(str_repeat('word ', 358)),
        ]);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->assertSee('358 words');
    }

    public function test_a_scene_with_no_contents_shows_zero_words_on_the_index(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['name' => 'Blank scene', 'contents' => '']);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->assertSee('0 words');
    }

    // ---------------------------------------------------------------------
    // Duplication (SceneDuplicator)
    // ---------------------------------------------------------------------

    public function test_duplicating_a_scene_creates_a_copy_and_redirects_to_its_edit_page(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Arrival']);

        $response = $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Arrival (2)']);

        $copy = Scene::where('name', 'Arrival (2)')->firstOrFail();
        $response->assertRedirect(route('scenes.edit', $copy));
        $this->assertSame('duplicated', session('status'));
        $this->assertSame($chapter->id, $copy->chapter_id);
        $this->assertSame(2, Scene::count());
    }

    public function test_a_non_owner_cannot_duplicate_a_scene(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($other)
            ->post(route('scenes.duplicate', $scene), ['name' => 'Copy'])
            ->assertForbidden();

        $this->assertSame(1, Scene::count());
    }

    public function test_duplicating_a_scene_without_a_name_fails_validation(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->post(route('scenes.duplicate', $scene), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Scene::count());
    }

    public function test_duplicating_a_scene_with_a_name_over_255_characters_fails_validation(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->post(route('scenes.duplicate', $scene), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Scene::count());
    }

    public function test_duplicating_a_scene_with_a_colliding_name_is_accepted(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Arrival']);

        $this->actingAs($user)
            ->post(route('scenes.duplicate', $scene), ['name' => 'Arrival'])
            ->assertRedirect();

        $this->assertSame(2, Scene::where('name', 'Arrival')->count());
    }

    public function test_duplicating_the_middle_scene_inserts_the_copy_right_after_it(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        $middle = Scene::factory()->for($chapter)->create(['position' => 2]);
        $last = Scene::factory()->for($chapter)->create(['position' => 3]);

        $this->actingAs($user)->post(route('scenes.duplicate', $middle), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $middle->fresh()->position);
        $this->assertSame(3, $copy->position);
        $this->assertSame(4, $last->fresh()->position);

        $ordered = $chapter->scenes()->orderBy('position')->orderBy('id')->pluck('name');
        $this->assertSame([$first->name, $middle->name, 'Copy', $last->name], $ordered->all());
    }

    public function test_duplicating_the_last_scene_appends_the_copy(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $first = Scene::factory()->for($chapter)->create(['position' => 1]);
        $last = Scene::factory()->for($chapter)->create(['position' => 2]);

        $this->actingAs($user)->post(route('scenes.duplicate', $last), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $last->fresh()->position);
        $this->assertSame(3, $copy->position);
    }

    public function test_duplicating_a_scene_never_renumbers_another_chapters_scenes(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapterOne = Chapter::factory()->for($act)->create();
        $chapterTwo = Chapter::factory()->for($act)->create();

        $sceneOne = Scene::factory()->for($chapterOne)->create(['position' => 1]);
        $sceneTwo = Scene::factory()->for($chapterTwo)->create(['position' => 1]);

        $this->actingAs($user)->post(route('scenes.duplicate', $sceneOne), ['name' => 'Copy']);

        $this->assertSame(1, $sceneTwo->fresh()->position);
    }

    public function test_duplicating_a_shared_scene_leaves_the_copy_unshared(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create([
            'share_token' => 'a-token',
            'share_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertNull($copy->share_token);
        $this->assertNull($copy->share_expires_at);
    }

    public function test_duplicating_a_scene_copies_status_contents_and_notes_verbatim(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create([
            'status' => SceneStatus::Final,
            'contents' => 'Some **markdown** contents.',
            'notes' => 'Private notes.',
        ]);

        $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertSame(SceneStatus::Final, $copy->status);
        $this->assertSame($scene->contents, $copy->contents);
        $this->assertSame('Private notes.', $copy->notes);
    }

    public function test_duplicating_a_scene_recomputes_rather_than_copies_word_count(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['contents' => trim(str_repeat('word ', 10))]);

        $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertSame(10, $copy->word_count);
        $this->assertSame($scene->word_count, $copy->word_count);
    }

    public function test_duplicating_a_scene_replicates_its_event_links_without_creating_events(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;
        $happensDuring = Event::factory()->for($project)->create();
        $mentioned = Event::factory()->for($project)->create();
        $scene = Scene::factory()->for($chapter)->create(['event_id' => $happensDuring->id]);
        $scene->mentionedEvents()->attach($mentioned->id);
        // A project auto-creates two fixed start/end events (Project::booted()); the
        // baseline is 4, not 0.
        $eventCountBeforeDuplicate = Event::count();

        $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        $this->assertSame($happensDuring->id, $copy->event_id);
        $this->assertSame([$mentioned->id], $copy->mentionedEvents()->pluck('events.id')->all());
        $this->assertSame($eventCountBeforeDuplicate, Event::count());
    }

    public function test_duplicating_a_scene_rebuilds_rather_than_copies_codex_references(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');
        // Factory-created, so no scene_codex_entry row exists yet — only a save that
        // goes through the controller (store/update/duplicate) triggers the sync.
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Mel walked into the room.']);
        $this->assertDatabaseMissing('scene_codex_entry', ['scene_id' => $scene->id]);

        $this->actingAs($user)->post(route('scenes.duplicate', $scene), ['name' => 'Copy']);
        $copy = Scene::where('name', 'Copy')->firstOrFail();

        // Rebuilt from the copy's own contents, which happen to match the same entry —
        // not copied from (the still-empty) original pivot set.
        $this->assertDatabaseHas('scene_codex_entry', ['scene_id' => $copy->id, 'codex_entry_id' => $entry->id]);
    }

    // ---------------------------------------------------------------------
    // Duplicate dialog UI
    // ---------------------------------------------------------------------

    public function test_the_scenes_index_shows_a_duplicate_trigger_with_the_suggested_name(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Arrival']);

        $this->actingAs($user)
            ->get(route('books.scenes.index', $chapter->act->book))
            ->assertOk()
            ->assertSee('duplicate-scene-'.$scene->id, false)
            ->assertSee('value="Arrival (2)"', false);
    }

    public function test_the_scene_edit_page_shows_a_duplicate_trigger_with_the_suggested_name(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Arrival']);

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('duplicate-scene-'.$scene->id, false)
            ->assertSee('value="Arrival (2)"', false);
    }
}
