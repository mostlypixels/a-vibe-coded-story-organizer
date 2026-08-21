<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the branded error pages under `resources/views/errors`. Without them
 * Laravel renders its own unstyled page, which carries none of the app's theme
 * or font choices.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_owner_gets_the_branded_403_page(): void
    {
        $project = Project::factory()->for(User::factory())->create();
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->get(route('projects.show', $project));

        $response->assertForbidden();
        $response->assertSee(__('This is not yours to open.'));
        $response->assertSee(__('Back to projects'));
    }

    public function test_the_error_bar_offers_the_project_picker_and_configuration(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Mine to keep']);
        // active_project_id is not fillable — only TrackActiveProject writes it.
        $user->forceFill(['active_project_id' => $project->id])->save();

        $response = $this->actingAs($user)->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('Mine to keep');
        $response->assertSee(__('All projects'));
        $response->assertSee(__('Configuration'));
    }

    public function test_the_error_bar_does_not_name_the_project_a_403_refused(): void
    {
        $project = Project::factory()->for(User::factory())->create(['name' => 'Secret novel']);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->get(route('projects.show', $project));

        $response->assertForbidden();
        $response->assertDontSee('Secret novel');
    }

    public function test_an_unknown_url_gets_the_branded_404_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee(__('We cannot find that page.'));
    }

    public function test_a_guest_on_an_unknown_url_is_sent_home_rather_than_to_the_project_list(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee(__('Back to home'));
        $response->assertDontSee(__('Back to projects'));
    }

    public function test_a_post_to_an_unmatched_url_still_404s_rather_than_405s(): void
    {
        // The catch-all that gives the 404 page its session must answer every
        // method. A GET-only one matches this URI and reports 405 instead.
        $response = $this->actingAs(User::factory()->create())->post('/no-such-page');

        $response->assertNotFound();
    }

    public function test_a_server_error_gets_the_branded_500_page_and_leaks_no_detail(): void
    {
        // Debug mode replaces the 500 view with the Ignition trace page, so the
        // rendered view is only reachable with it off.
        config(['app.debug' => false]);

        Route::get('/test-error-page', fn () => throw new \RuntimeException('database password is hunter2'));

        $response = $this->get('/test-error-page');

        $response->assertStatus(500);
        $response->assertSee(__('Something went wrong on our side.'));
        $response->assertDontSee('hunter2');
    }
}
