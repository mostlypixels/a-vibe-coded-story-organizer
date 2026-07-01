<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_the_authenticated_users_projects_alphabetically(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Zebra']);
        Project::factory()->for($user)->create(['name' => 'Apple']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeInOrder(['Apple', 'Zebra']);
    }

    public function test_a_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'My Project',
            'description' => 'A test project',
        ]);

        $project = Project::first();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame($user->id, $project->user_id);
    }

    public function test_a_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_a_user_can_add_a_plotline_to_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
            'description' => 'Some description',
        ]);

        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame(1, $project->plotlines()->count());
    }

    public function test_a_user_cannot_add_a_plotline_to_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->post(route('projects.plotlines.store', $project), [
            'name' => 'A Plotline',
        ])->assertForbidden();
    }
}
