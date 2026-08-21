<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A new account with no projects is sent to onboarding, which prompts for the
 * first project. The site logo follows the same rule: the active project's
 * dashboard when there is one, the project list otherwise.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_project_list_redirects_an_empty_account_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertRedirect(route('onboarding'));
    }

    public function test_onboarding_prompts_for_the_first_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertSee(__('Create your first project'))
            ->assertSee(e(route('projects.create')), false);
    }

    public function test_onboarding_bounces_an_account_that_already_has_a_project(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertRedirect(route('projects.index'));
    }

    public function test_the_logo_points_at_the_active_project_dashboard(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->forceFill(['active_project_id' => $project->id])->save();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->getContent();

        $this->assertStringContainsString('href="'.e(route('projects.show', $project)).'"', $html);
    }

    public function test_the_logo_points_at_the_project_list_without_an_active_project(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        $html = $this->actingAs($user)->get(route('projects.index'))->getContent();

        $this->assertStringContainsString('href="'.e(route('projects.index')).'"', $html);
    }
}
