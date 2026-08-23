<?php

namespace Tests\Feature;

use App\Enums\Genre;
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

        $response = $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertSee(__("Hi! Let's make your first project. You can change anything here later."))
            ->assertSee(e(route('onboarding.store')), false)
            ->assertSee(e(route('onboarding.demo')), false);

        // Every genre except Blank is a picker tile. Blank is reached through the
        // "Skip and start blank" link, not a tile.
        foreach (Genre::cases() as $genre) {
            if ($genre === Genre::Blank) {
                continue;
            }

            $response->assertSee(__($genre->label()));
        }
    }

    public function test_onboarding_renders_the_genre_options_demo_action_and_skip_control(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertSee(__('Pick your genre'))
            ->assertSee(__('Install demo projects'))
            ->assertSee(__('Skip and start blank'))
            ->assertSee('value="'.Genre::Blank->value.'"', false)
            // Blank is the skip link, not a genre tile.
            ->assertDontSee(__(Genre::Blank->description()));
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

    public function test_store_seeds_a_genre_project_and_redirects_with_a_hint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.store'), [
            'name' => 'My Fantasy Novel',
            'genre' => Genre::Fantasy->value,
        ]);

        $project = Project::where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('status', 'onboarding-seeded');
        $this->assertSame('My Fantasy Novel', $project->name);
        $this->assertSame(Genre::Fantasy, $project->genre);
        $this->assertSame(1, $project->books()->count());
    }

    public function test_store_with_blank_genre_creates_an_empty_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), [
            'name' => 'Untitled',
            'genre' => Genre::Blank->value,
        ])->assertRedirect();

        $project = Project::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(Genre::Blank, $project->genre);
        $this->assertSame(0, $project->codexAttributes()->count());
        $this->assertSame(1, $project->books()->count());
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), [
            'genre' => Genre::Fantasy->value,
        ])->assertSessionHasErrors('name');
    }

    public function test_store_rejects_an_unknown_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), [
            'name' => 'My Novel',
            'genre' => 'not-a-genre',
        ])->assertSessionHasErrors('genre');
    }

    public function test_install_demo_seeds_only_the_acting_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.demo'))
            ->assertRedirect(route('projects.index'));

        $this->assertSame(3, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Project::where('user_id', $otherUser->id)->count());
    }

    public function test_the_project_page_shows_the_post_seed_hint_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.store'), [
            'name' => 'My Fantasy Novel',
            'genre' => Genre::Fantasy->value,
        ]);

        $project = Project::where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->followRedirects($response)
            ->assertSee("Here's your project. We added some starter attributes, tags, and a first book.");
    }

    public function test_a_normal_visit_to_the_project_page_does_not_show_the_post_seed_hint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee("Here's your project. We added some starter attributes, tags, and a first book.");
    }

    public function test_onboarding_routes_redirect_a_guest_to_login(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
        $this->post(route('onboarding.store'))->assertRedirect(route('login'));
        $this->post(route('onboarding.demo'))->assertRedirect(route('login'));
    }
}
