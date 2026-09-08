<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The HTTP surface of the quick codex entry: SceneCodexEntryController::store(),
 * StoreQuickCodexEntryRequest, and CodexEntrySaver::create()'s rescanProject switch.
 */
class SceneCodexEntryTest extends TestCase
{
    use RefreshDatabase;

    /** Build a full project -> act -> chapter -> scene chain owned by the given user. */
    private function sceneFor(User $user, array $overrides = []): Scene
    {
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create($overrides);
    }

    private function project(Scene $scene): Project
    {
        return $scene->chapter->act->book->project;
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_it_creates_the_entry_and_returns_its_id_and_url(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $response = $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();

        $response->assertJsonPath('entry.id', $entry->id);
        $response->assertJsonPath('entry.name', 'Melusine');
        $response->assertJsonPath('entry.type', 'character');
        $response->assertJsonPath('entry.type_label', 'Character');
        $response->assertJsonPath('entry.url', route('codex.show', $entry));
    }

    public function test_it_leaves_the_scene_untouched(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => '<p>Fixed prose.</p>']);
        $updatedAt = $scene->updated_at;
        $wordCount = $scene->word_count;

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $fresh = $scene->fresh();
        $this->assertTrue($updatedAt->equalTo($fresh->updated_at));
        $this->assertSame('<p>Fixed prose.</p>', $fresh->contents);
        $this->assertSame($wordCount, $fresh->word_count);
    }

    public function test_the_returned_list_includes_the_entry_when_the_contents_hold_the_name(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => '<p>Melusine walked in.</p>']);

        $response = $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $this->assertStringContainsString('Melusine', $response->json('referenced_entries_html'));
    }

    public function test_the_returned_list_excludes_the_entry_when_the_contents_do_not_hold_the_name(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => '<p>Nothing relevant here.</p>']);

        $response = $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $this->assertStringNotContainsString('Melusine', $response->json('referenced_entries_html'));
    }

    public function test_a_quick_created_entry_has_no_attribute_values(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $this->assertSame(0, $entry->attributeValues()->count());
    }

    // ---------------------------------------------------------------------
    // Accepted debt: no project-wide rescan
    // ---------------------------------------------------------------------

    public function test_it_does_not_rescan_the_rest_of_the_project_so_another_scenes_pivot_stays_stale(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => '<p>Melusine appears here too.</p>']);
        $project = $this->project($scene);

        $act = Act::factory()->for($project->books()->first())->create();
        $chapter = Chapter::factory()->for($act)->create();
        $otherScene = Scene::factory()->for($chapter)->create(['contents' => '<p>Melusine appears here too.</p>']);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertOk();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();

        $this->assertTrue($scene->fresh()->codexReferences->contains($entry));
        $this->assertFalse($otherScene->fresh()->codexReferences->contains($entry), 'accepted debt: only the prompting scene is resynced');
    }

    // ---------------------------------------------------------------------
    // Duplicate names
    // ---------------------------------------------------------------------

    public function test_a_duplicate_name_of_the_same_type_is_refused_with_the_existing_entry_id(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $project = $this->project($scene);
        $existing = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'melusine', 'type' => CodexEntryType::Character->value],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('existing_entry_id', $existing->id);
        $this->assertSame(1, CodexEntry::where('type', CodexEntryType::Character->value)->count());
    }

    public function test_the_same_name_with_a_different_type_is_allowed(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $project = $this->project($scene);
        CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Location->value],
        )->assertOk();

        $this->assertSame(2, CodexEntry::where('name', 'Melusine')->count());
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_a_non_owner_cannot_create_an_entry(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scene = $this->sceneFor($owner);

        $this->actingAs($other)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertForbidden();

        $this->assertSame(0, CodexEntry::count());
    }

    public function test_a_scene_from_another_project_gets_forbidden_not_a_missing_route(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $sceneOfA = $this->sceneFor($userA);
        // userB has their own project, but no access to userA's scene.
        $this->sceneFor($userB);

        $this->actingAs($userB)->postJson(
            route('scenes.codex-entries.store', $sceneOfA),
            ['name' => 'Melusine', 'type' => CodexEntryType::Character->value],
        )->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    public function test_a_blank_name_is_rejected(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => '', 'type' => CodexEntryType::Character->value],
        )->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_a_whitespace_only_name_is_rejected(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => '   ', 'type' => CodexEntryType::Character->value],
        )->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_a_name_over_255_characters_is_rejected(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => str_repeat('a', 256), 'type' => CodexEntryType::Character->value],
        )->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)->postJson(
            route('scenes.codex-entries.store', $scene),
            ['name' => 'Melusine', 'type' => 'dragon'],
        )->assertStatus(422)->assertJsonValidationErrors('type');
    }

    // ---------------------------------------------------------------------
    // Regression: the full form still rescans the project
    // ---------------------------------------------------------------------

    public function test_the_full_codex_create_form_still_rescans_the_project(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, ['contents' => '<p>Melusine appears here too.</p>']);
        $project = $this->project($scene);

        $act = Act::factory()->for($project->books()->first())->create();
        $chapter = Chapter::factory()->for($act)->create();
        $otherScene = Scene::factory()->for($chapter)->create(['contents' => '<p>Melusine appears here too.</p>']);

        $this->actingAs($user)->post(
            route('projects.codex.store', [$project, 'characters']),
            ['name' => 'Melusine'],
        )->assertRedirect();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();

        $this->assertTrue($otherScene->fresh()->codexReferences->contains($entry));
    }
}
