<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\Act;
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

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

        $project = $chapter->act->project;

        $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->assertSee('A memorable scene');
    }

    public function test_a_user_cannot_view_scenes_of_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);

        $this->actingAs($other)
            ->get(route('projects.scenes.index', $chapter->act->project))
            ->assertForbidden();
    }

    public function test_a_user_can_create_a_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $response = $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter));

        $response->assertRedirect(route('projects.scenes.index', $project));

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
        $project = $chapter->act->project;

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['name' => 'First']));
        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['name' => 'Second']));

        $this->assertSame(1, Scene::where('name', 'First')->value('position'));
        $this->assertSame(2, Scene::where('name', 'Second')->value('position'));
    }

    public function test_a_user_cannot_create_a_scene_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $project = $chapter->act->project;

        $this->actingAs($other)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter))
            ->assertForbidden();

        $this->assertSame(0, Scene::count());
    }

    public function test_scene_creation_requires_a_name(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_scene_creation_requires_a_valid_status(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['status' => 'not-a-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_a_scene_cannot_be_attached_to_a_chapter_from_another_project(): void
    {
        $user = User::factory()->create();
        $ownProject = Project::factory()->for($user)->create();
        $foreignChapter = $this->chapterFor($user); // belongs to a different project

        // Posting to $ownProject with a chapter_id that lives outside it must fail validation.
        $this->actingAs($user)
            ->post(route('projects.scenes.store', $ownProject), $this->validPayload($foreignChapter))
            ->assertSessionHasErrors('chapter_id');

        $this->assertSame(0, Scene::count());
    }

    public function test_a_user_can_update_a_scene(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Old name']);
        $project = $chapter->act->project;

        $response = $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['name' => 'New name', 'status' => SceneStatus::Final->value]),
        );

        $response->assertRedirect(route('projects.scenes.index', $project));

        $scene = $scene->fresh();
        $this->assertSame('New name', $scene->name);
        $this->assertSame(SceneStatus::Final, $scene->status);
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
        $project = $chapter->act->project;

        $this->actingAs($user)
            ->delete(route('scenes.destroy', $scene))
            ->assertRedirect(route('projects.scenes.index', $project));

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
        $project = $chapter->act->project;

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter))
            ->assertRedirect(route('projects.scenes.index', $project));

        $this->assertNull(Scene::first()->event_id);
    }

    public function test_a_scene_can_happen_during_an_existing_event(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $event = Event::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['event_id' => $event->id]));

        $this->assertSame($event->id, Scene::first()->event_id);
    }

    public function test_the_inline_new_event_form_creates_an_event_attached_to_the_main_plotline(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $this->actingAs($user)->post(route('projects.scenes.store', $project), $this->validPayload($chapter, [
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
        $project = $chapter->act->project;
        $foreignEvent = Event::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['event_id' => $foreignEvent->id]))
            ->assertSessionHasErrors('event_id');
    }

    public function test_a_scene_can_mention_multiple_events(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $first = Event::factory()->for($project)->create();
        $second = Event::factory()->for($project)->create();

        $this->actingAs($user)->post(
            route('projects.scenes.store', $project),
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
        $project = $chapter->act->project;
        $foreignEvent = Event::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->post(route('projects.scenes.store', $project), $this->validPayload($chapter, ['mentioned_events' => [$foreignEvent->id]]))
            ->assertSessionHasErrors('mentioned_events.0');

        $this->assertSame(0, Scene::count());
    }

    public function test_deleting_an_event_unassigns_scenes_and_clears_mentions(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
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
            ->get(route('projects.scenes.index', $chapter->act->project))
            ->assertOk()
            ->assertSee('Unassigned');
    }

    public function test_the_scene_form_renders_the_event_controls(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->get(route('projects.scenes.create', $project))
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
        $project = $chapter->act->project;
        $event = Event::factory()->for($project)->create(['title' => 'The Coronation']);
        Scene::factory()->for($chapter)->create(['event_id' => $event->id]);
        Scene::factory()->for($chapter)->create(['event_id' => null]);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
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
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
        $project = $chapter->act->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');

        $this->actingAs($user)->post(
            route('projects.scenes.store', $project),
            $this->validPayload($chapter, ['contents' => 'Mel walked into the room.']),
        )->assertRedirect(route('projects.scenes.index', $project));

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => Scene::first()->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_updating_a_scene_adds_then_removes_the_reference_row(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $entry = $this->codexEntryIn($project, 'Melchior', 'Mel');
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Nobody here.']);

        // Update to add mentioning text → row created.
        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'Then Mel arrived.']),
        )->assertRedirect(route('projects.scenes.index', $project));

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);

        // Update again to remove the mention → the stale row is dropped (full resync).
        $this->actingAs($user)->put(
            route('scenes.update', $scene),
            $this->validPayload($chapter, ['contents' => 'The room is empty again.']),
        )->assertRedirect(route('projects.scenes.index', $project));

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_saving_a_scene_with_no_matches_leaves_no_reference_rows(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $this->codexEntryIn($project, 'Melchior', 'Mel');

        $this->actingAs($user)->post(
            route('projects.scenes.store', $project),
            $this->validPayload($chapter, ['contents' => 'Just an ordinary melody, nothing more.']),
        )->assertRedirect(route('projects.scenes.index', $project));

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => Scene::first()->id,
        ]);
    }

    public function test_a_non_owner_cannot_update_a_scene_and_no_references_are_recomputed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapterFor($owner);
        $project = $chapter->act->project;
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

    // --- Task 06: "Codex references" sidebar on the scene edit page ----------

    public function test_the_edit_page_lists_referenced_codex_entries_linking_to_their_edit_pages(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $scene = Scene::factory()->for($chapter)->create();
        $entry = $this->codexEntryIn($project, 'Melchior');

        // Seed the pivot directly — this read-path UI renders whatever the matcher last wrote.
        $scene->codexReferences()->attach($entry);

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertOk()
            ->assertSee('Codex references')
            ->assertSee('Melchior')
            ->assertSee($entry->type->label())
            ->assertSee(route('codex.edit', $entry), escape: false);
    }

    public function test_the_edit_page_shows_the_last_save_caption(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->for($this->chapterFor($user))->create();

        $this->actingAs($user)->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('Detected from the scene contents on last save.');
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
        $project = $chapter->act->project;
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
}
