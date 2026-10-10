<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\User;
use App\Support\AutosavableFields;
use App\Support\FieldHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

/**
 * #258: a full-form save must not overwrite text that another tab or device saved
 * after the page loaded.
 */
class ManualSaveConflictTest extends TestCase
{
    use RefreshDatabase;

    private function sceneFor(User $user, string $contents): Scene
    {
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        return Scene::factory()->for($chapter)->create(['contents' => $contents]);
    }

    /** @return array<string, mixed> */
    private function scenePayload(Scene $scene, array $overrides = []): array
    {
        return array_merge([
            'chapter_id' => $scene->chapter_id,
            'name' => 'Renamed',
            'description' => (string) $scene->description,
            'contents' => 'My text',
            'status' => SceneStatus::Draft->value,
        ], $overrides);
    }

    public function test_a_stale_base_hash_keeps_the_newer_text_and_saves_nothing(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Newer text');

        $this->actingAs($user)
            ->from(route('scenes.edit', $scene))
            ->put(route('scenes.update', $scene), $this->scenePayload($scene, [
                'base_hashes' => ['contents' => FieldHash::of('Text the page loaded')],
            ]))
            ->assertRedirect(route('scenes.edit', $scene))
            ->assertSessionHasErrors('base_hashes.contents')
            ->assertSessionHasInput('contents', 'My text');

        $scene->refresh();
        $this->assertSame('Newer text', $scene->contents);
        $this->assertNotSame('Renamed', $scene->name);
    }

    public function test_an_autosave_that_lands_after_validation_keeps_its_text(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Text the page loaded');

        // This callback runs after the form request has validated the hashes.
        $this->app->afterResolving(UpdateSceneRequest::class, fn () => Scene::query()
            ->whereKey($scene->id)
            ->update(['contents' => 'Autosaved in another tab']));

        $this->actingAs($user)
            ->from(route('scenes.edit', $scene))
            ->put(route('scenes.update', $scene), $this->scenePayload($scene, [
                'base_hashes' => ['contents' => FieldHash::of('Text the page loaded')],
            ]))
            ->assertRedirect(route('scenes.edit', $scene))
            ->assertSessionHasErrors('base_hashes.contents');

        $scene->refresh();
        $this->assertSame('Autosaved in another tab', $scene->contents);
        $this->assertNotSame('Renamed', $scene->name);
    }

    public function test_the_edit_page_after_a_conflict_shows_the_writers_text_and_the_choice(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Newer text');

        $this->actingAs($user)
            ->from(route('scenes.edit', $scene))
            ->put(route('scenes.update', $scene), $this->scenePayload($scene, [
                'contents' => 'My unsaved words',
                'base_hashes' => ['contents' => FieldHash::of('Text the page loaded')],
            ]));

        $this->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('My unsaved words')
            ->assertSee('conflict: '.Js::from(['value' => 'Newer text', 'hash' => FieldHash::of('Newer text')]), false);
    }

    public function test_a_current_base_hash_saves(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Loaded text');

        $this->actingAs($user)
            ->put(route('scenes.update', $scene), $this->scenePayload($scene, [
                'base_hashes' => ['contents' => FieldHash::of('Loaded text')],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('My text', $scene->fresh()->contents);
    }

    public function test_a_form_without_base_hashes_saves_as_before(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Loaded text');

        $this->actingAs($user)
            ->put(route('scenes.update', $scene), $this->scenePayload($scene))
            ->assertSessionHasNoErrors();

        $this->assertSame('My text', $scene->fresh()->contents);
    }

    public function test_the_edit_page_sends_the_base_hash_of_each_autosaved_field(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user, 'Loaded text');

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertSee('name="base_hashes[contents]" value="'.FieldHash::of('Loaded text').'"', false);
    }

    public function test_an_act_save_checks_the_description_hash(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => 'Old', 'description' => 'Newer']);

        $this->actingAs($user)
            ->put(route('acts.update', $act), [
                'name' => 'New',
                'description' => 'Mine',
                'base_hashes' => ['description' => FieldHash::of('Loaded')],
            ])
            ->assertSessionHasErrors('base_hashes.description');

        $this->assertSame('Newer', $act->fresh()->description);
    }

    /** A new entity with autosaved fields must get the check too. */
    public function test_every_update_request_of_an_autosaved_entity_checks_the_hashes(): void
    {
        foreach (AutosavableFields::slugs() as $slug) {
            $request = 'App\\Http\\Requests\\Update'.class_basename(AutosavableFields::modelFor($slug)).'Request';

            $this->assertTrue(class_exists($request), "$request is missing");
            $this->assertTrue(method_exists($request, 'after'), "$request has no after() hook with NoAutosaveConflict");
        }
    }
}
