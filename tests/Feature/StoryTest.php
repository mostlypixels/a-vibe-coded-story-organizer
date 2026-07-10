<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the read-only Story overview: it authorizes via the project
 * and renders the nested act -> chapter -> scene tree ordered by `position`.
 */
class StoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_story_overview_renders_the_full_act_chapter_scene_tree(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['name' => 'The First Act']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The First Chapter']);
        Scene::factory()->for($chapter)->create(['name' => 'The First Scene']);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSee('The First Act')
            ->assertSee('The First Chapter')
            ->assertSee('The First Scene');
    }

    public function test_a_user_cannot_view_another_users_story_overview(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.story.index', $project))
            ->assertForbidden();
    }

    public function test_the_story_overview_orders_acts_by_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // Create out of position order to prove the view sorts, not insertion order.
        Act::factory()->for($project)->create(['name' => 'Later Act', 'position' => 2]);
        Act::factory()->for($project)->create(['name' => 'Earlier Act', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSeeInOrder(['Earlier Act', 'Later Act']);
    }

    public function test_the_story_overview_orders_scenes_within_a_chapter_by_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        Scene::factory()->for($chapter)->create(['name' => 'Second Scene', 'position' => 2]);
        Scene::factory()->for($chapter)->create(['name' => 'First Scene', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSeeInOrder(['First Scene', 'Second Scene']);
    }
}
