<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\Act;
use App\Models\Chapter;
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
}
