<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The four top-level section landing pages (Story/Timeline/Codex/Tools): every
 * one authorizes via ProjectPolicy and lights up its own top-level menu item.
 * Their breadcrumb trails are pinned in Tests\Unit\BreadcrumbsTest.
 *
 * Story/Timeline/Codex render "recently edited" content, covered by
 * Tests\Feature\RecentlyEditedTest. Tools links to the other tools instead —
 * it owns no user-authored entity of its own, so there is nothing recent to
 * list there. Its cards are covered below.
 */
class SectionStubTest extends TestCase
{
    use RefreshDatabase;

    /** Every section route, for the auth cases that don't need the heading. */
    public static function stubRouteNames(): array
    {
        return [
            'story' => ['projects.story.home'],
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
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.story.overview', $project))
            ->assertOk()
            ->assertSee('Story Overview'); // the page heading keeps the section name
    }
}
