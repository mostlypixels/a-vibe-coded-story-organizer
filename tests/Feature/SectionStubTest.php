<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SectionStubTest extends TestCase
{
    use RefreshDatabase;

    public static function stubRouteNames(): array
    {
        return [
            'story' => ['books.story.home'],
            'timeline' => ['projects.timeline.home'],
            'codex' => ['projects.codex.home'],
            'tools' => ['projects.tools.home'],
        ];
    }

    public function test_the_tools_page_links_to_revisions_and_progress(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.tools.home', $project))
            ->assertOk()
            ->assertSee('Tools')
            ->assertSee(route('projects.revisions.index', $project), false)
            ->assertSee(route('projects.progress', $project), false);
    }

    #[DataProvider('stubRouteNames')]
    public function test_a_non_owner_is_forbidden(string $route): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())
            ->get(route($route, $project))
            ->assertForbidden();
    }

    #[DataProvider('stubRouteNames')]
    public function test_a_guest_is_redirected_to_login(string $route): void
    {
        $project = Project::factory()->create();

        $this->get(route($route, $project))->assertRedirect(route('login'));
    }

    public function test_the_moved_story_overview_still_renders_at_its_new_url(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('Story Overview'); // the page heading keeps the section name
    }
}
