<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAlias;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\User;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_a_type_index_and_it_is_isolated_by_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        CodexEntry::factory()->for($project)->location()->create(['name' => 'Lusignan Castle']);

        $characters = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']));
        $characters->assertOk();
        $characters->assertSee('Melusine');
        $characters->assertDontSee('Lusignan Castle');

        $locations = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'locations']));
        $locations->assertSee('Lusignan Castle');
        $locations->assertDontSee('Melusine');
    }

    public function test_index_search_matches_name_and_alias(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $melusine = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $melusine->aliases()->create(['alias' => 'The Serpent Lady']);

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin']);

        $byName = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'search' => 'Melusine']));
        $byName->assertSee('Melusine');
        $byName->assertDontSee('Raymondin');

        $byAlias = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'search' => 'Serpent']));
        $byAlias->assertSee('Melusine');
        $byAlias->assertDontSee('Raymondin');
    }

    public function test_index_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create(['name' => 'Fae']);

        $tagged = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $tagged->tags()->attach($tag);

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin']);

        $response = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'tag' => $tag->id]));
        $response->assertSee('Melusine');
        $response->assertDontSee('Raymondin');
    }

    public function test_owner_can_render_the_create_and_edit_forms(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $entry->aliases()->create(['alias' => 'The Serpent Lady']);

        $this->actingAs($user)->get(route('projects.codex.create', [$project, 'characters']))
            ->assertOk()
            ->assertSee('New Character');

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('Melusine');
    }

    public function test_create_and_edit_forms_receive_project_tags_ordered_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Tag::factory()->for($project)->create(['name' => 'Nobility']);
        Tag::factory()->for($project)->create(['name' => 'Fae']);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->get(route('projects.codex.create', [$project, 'characters']))
            ->assertOk()
            ->assertViewHas('projectTags', fn ($projectTags) => $projectTags->pluck('name')->all() === ['Fae', 'Nobility']);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertViewHas('projectTags', fn ($projectTags) => $projectTags->pluck('name')->all() === ['Fae', 'Nobility']);
    }

    public function test_index_tag_filter_omits_tags_with_no_entries(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $attached = Tag::factory()->for($project)->create(['name' => 'Fae']);
        $orphan = Tag::factory()->for($project)->create(['name' => 'Unused']);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $entry->tags()->attach($attached);

        $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->assertViewHas('tags', function ($tags) use ($attached, $orphan) {
                return $tags->contains($attached) && ! $tags->contains($orphan);
            });
    }

    public function test_owner_can_create_an_entry_with_aliases_and_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $existingTag = Tag::factory()->for($project)->create(['name' => 'Fae']);

        $response = $this->actingAs($user)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => 'Melusine',
            'description' => 'A **serpent** from the waist down.',
            'aliases' => ['The Serpent Lady', 'Mère Lusignan'],
            'tags' => ['Fae', 'Nobility'],
        ]);

        $response->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $this->assertSame($project->id, $entry->project_id);
        $this->assertSame('character', $entry->type->value);
        $this->assertSame('A **serpent** from the waist down.', $entry->description);
        $this->assertEqualsCanonicalizing(
            ['The Serpent Lady', 'Mère Lusignan'],
            $entry->aliases()->pluck('alias')->all()
        );

        // Existing tag reused (not duplicated), new tag firstOrCreate'd within the project.
        $this->assertSame(2, $project->tags()->count());
        $this->assertTrue($entry->tags->contains($existingTag));
        $this->assertEqualsCanonicalizing(['Fae', 'Nobility'], $entry->tags->pluck('name')->all());
    }

    public function test_store_creates_project_scoped_tags_without_reusing_another_projects_tag(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $foreignTag = Tag::factory()->for($projectB)->create(['name' => 'Fae']);

        $this->actingAs($user)->post(route('projects.codex.store', [$projectA, 'characters']), [
            'name' => 'Melusine',
            'tags' => ['Fae'],
        ])->assertRedirect();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $ownTag = $entry->tags->firstOrFail();

        // A fresh, project-scoped tag was created rather than attaching project B's row.
        $this->assertSame($projectA->id, $ownTag->project_id);
        $this->assertNotSame($foreignTag->id, $ownTag->id);
        $this->assertSame(1, $projectA->tags()->count());
    }

    public function test_owner_can_update_an_entry(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Old Name']);
        $entry->aliases()->create(['alias' => 'Stale Alias']);

        $response = $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'New Name',
            'description' => 'Updated.',
            'aliases' => ['Fresh Alias'],
            'tags' => ['Reworked'],
        ]);

        $response->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $entry->refresh();
        $this->assertSame('New Name', $entry->name);
        $this->assertSame(['Fresh Alias'], $entry->aliases()->pluck('alias')->all());
        $this->assertSame(['Reworked'], $entry->tags->pluck('name')->all());
    }

    // ---------------------------------------------------------------------
    // Codex reference matching on save (SceneReferenceMatcher wiring)
    // ---------------------------------------------------------------------

    /**
     * Seed a scene with the given contents inside a fresh act -> chapter chain in $project.
     * Optionally name it and assign it to an event (for the timeline-order sidebar tests).
     */
    private function sceneIn(Project $project, string $contents, ?string $name = null, ?Event $event = null): Scene
    {
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $attributes = ['contents' => $contents];

        if ($name !== null) {
            $attributes['name'] = $name;
        }

        if ($event !== null) {
            $attributes['event_id'] = $event->id;
        }

        return Scene::factory()->for($chapter)->create($attributes);
    }

    public function test_creating_an_entry_links_scenes_whose_contents_already_match(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'Mel walked into the room.');

        // A brand-new entry always rescans the project — the existing scene links with no
        // scene re-save required.
        $this->actingAs($user)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => 'Melchior',
            'aliases' => ['Mel'],
        ])->assertRedirect();

        $entry = CodexEntry::where('name', 'Melchior')->firstOrFail();
        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_editing_an_entry_to_add_a_matching_alias_links_the_scene(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'Then Mel arrived.');
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);

        // No alias yet matched "Mel", so the scene is unlinked to start.
        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melchior',
            'aliases' => ['Mel'],
        ])->assertRedirect();

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_editing_an_entry_so_it_no_longer_matches_removes_the_row(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $scene = $this->sceneIn($project, 'Then Mel arrived.');
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);

        // Establish the link via a first save that newly adds the matching alias (a term
        // change, so it rescans and links the scene).
        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melchior',
            'aliases' => ['Mel'],
        ])->assertRedirect();

        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);

        // Drop the matching alias → the stale pivot row is removed by the full resync.
        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melchior',
            'aliases' => ['Balthazar'],
        ])->assertRedirect();

        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_editing_an_entry_without_changing_terms_does_not_rescan(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melchior']);
        $entry->aliases()->create(['alias' => 'Mel']);

        // A project rescan is O(scenes); an edit that leaves the name and alias set untouched
        // (only the description here) must never trigger one — assert on the service directly.
        $this->mock(SceneReferenceMatcher::class)
            ->shouldReceive('syncProject')
            ->never();

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melchior',
            'description' => 'A newly written description.',
            'aliases' => ['Mel'],
        ])->assertRedirect();

        $entry->refresh();
        $this->assertSame('A newly written description.', $entry->description);
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => '',
        ])->assertSessionHasErrors('name');
    }

    public function test_unknown_type_segment_returns_404(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Build a valid URL then swap in an unknown type — the route `whereIn`
        // constraint should 404 before the controller runs.
        $url = str_replace(
            '/characters',
            '/dragons',
            route('projects.codex.index', [$project, 'characters'])
        );

        $this->actingAs($user)->get($url)->assertNotFound();
    }

    public function test_navigation_lists_every_codex_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']));
        $response->assertOk();

        foreach (CodexEntryType::cases() as $codexType) {
            $response->assertSee($codexType->pluralLabel());
        }
    }

    public function test_destroy_cascades_aliases_tags_and_attribute_values(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create();
        $alias = $entry->aliases()->create(['alias' => 'Doomed']);
        $tag = Tag::factory()->for($project)->create();
        $entry->tags()->attach($tag);
        $attribute = CodexAttribute::factory()->for($project)->create();
        $value = CodexAttributeValue::factory()->for($entry, 'entry')->create(['codex_attribute_id' => $attribute->id]);

        // A referencing scene's pivot row must cascade too (DB-level cascadeOnDelete, task 01).
        $scene = $this->sceneIn($project, 'Contents.');
        $scene->codexReferences()->attach($entry);

        $this->actingAs($user)->delete(route('codex.destroy', $entry))
            ->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $this->assertNull($entry->fresh());
        $this->assertNull(CodexAlias::find($alias->id));
        $this->assertNull(CodexAttributeValue::find($value->id));
        $this->assertDatabaseMissing('codex_entry_tag', ['codex_entry_id' => $entry->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseMissing('scene_codex_entry', ['codex_entry_id' => $entry->id]);
    }

    // ---------------------------------------------------------------------
    // Edit-page "Referenced in scenes" sidebar (read side of scene_codex_entry)
    // ---------------------------------------------------------------------

    public function test_edit_page_shows_the_alias_matching_help_text(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('Matching is case-sensitive and whole-word only', false);
    }

    public function test_edit_page_lists_referencing_scenes_in_event_timeline_order(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        // Two events, deliberately created in the opposite order to their datetimes, so a
        // creation-order render would fail this test — only the (event_datetime, id) sort passes.
        $laterEvent = Event::factory()->for($project)->create([
            'title' => 'The Coronation',
            'event_datetime' => now()->addDays(10),
        ]);
        $earlierEvent = Event::factory()->for($project)->create([
            'title' => 'The Betrothal',
            'event_datetime' => now()->addDays(2),
        ]);

        // Seed the scenes in the "wrong" order too, and attach the pivot rows directly — this
        // read path renders whatever is in scene_codex_entry, independent of the matcher.
        $laterScene = $this->sceneIn($project, 'Contents.', 'Scene at the coronation', $laterEvent);
        $earlierScene = $this->sceneIn($project, 'Contents.', 'Scene at the betrothal', $earlierEvent);
        $entry->referencingScenes()->attach([$laterScene->id, $earlierScene->id]);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSeeInOrder(['Scene at the betrothal', 'Scene at the coronation']);
    }

    public function test_edit_page_lists_scenes_without_an_event_last_and_labelled(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $event = Event::factory()->for($project)->create([
            'title' => 'The Betrothal',
            'event_datetime' => now()->addDays(2),
        ]);
        $assignedScene = $this->sceneIn($project, 'Contents.', 'Scene with an event', $event);
        $unassignedScene = $this->sceneIn($project, 'Contents.', 'Scene without an event');

        // Attach the unassigned scene first, so a naive "as attached" order would put it first —
        // the sort must still push it after the assigned scene.
        $entry->referencingScenes()->attach([$unassignedScene->id, $assignedScene->id]);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSeeInOrder(['Scene with an event', 'Scene without an event'])
            ->assertSee('No event assigned');
    }

    public function test_edit_page_shows_empty_state_when_no_scenes_reference_the_entry(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('No scenes reference this entry yet.');
    }

    public function test_non_owner_is_forbidden_from_every_action(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($other)->get(route('projects.codex.index', [$project, 'characters']))->assertForbidden();
        $this->actingAs($other)->get(route('projects.codex.create', [$project, 'characters']))->assertForbidden();
        $this->actingAs($other)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => 'Sneaky',
        ])->assertForbidden();
        $this->actingAs($other)->get(route('codex.edit', $entry))->assertForbidden();
        $this->actingAs($other)->put(route('codex.update', $entry), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->delete(route('codex.destroy', $entry))->assertForbidden();
    }
}
