<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the active-section highlighting in the primary navigation dropdowns.
 *
 * The nav renders on every authenticated page, so we exercise it through the
 * ordinary resource routes. We assert on the semantic `aria-current="page"`
 * marker (emitted only by the active desktop dropdown item) and on hrefs —
 * never on cosmetic Tailwind classes, which churn. The one exception is the
 * collapsed trigger, which has no better hook than its active class token.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a project -> act -> chapter chain owned by the given user and
     * return the leaf chapter (scenes hang off chapters).
     */
    private function chapterFor(User $user): Chapter
    {
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        return Chapter::factory()->for($act)->create();
    }

    /**
     * Assert that an <a> anchor pointing at $href carries aria-current="page".
     * The dropdown-link renders `<a class="…" href="…" aria-current="page">`,
     * so href precedes the marker.
     */
    private function assertLinkIsCurrent(string $html, string $href, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(e($href), '/').'"[^>]*aria-current="page"/',
            $html,
            $message,
        );
    }

    /**
     * Assert that the anchor for $href is present but is NOT the aria-current one.
     */
    private function assertLinkIsNotCurrent(string $html, string $href, string $message = ''): void
    {
        $this->assertStringContainsString('href="'.e($href).'"', $html, $message);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="'.preg_quote(e($href), '/').'"[^>]*aria-current="page"/',
            $html,
            $message,
        );
    }

    public function test_the_active_story_item_is_marked_on_a_story_page(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $html = $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.scenes.index', $project));
    }

    public function test_a_non_active_sibling_is_not_marked(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $html = $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->getContent();

        // Guards against over-broad matchers ("everything highlights"): on the
        // Scenes page the Acts item must be present but not current.
        $this->assertLinkIsNotCurrent($html, route('projects.acts.index', $project));
    }

    public function test_a_child_route_still_highlights_its_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;
        $scene = Scene::factory()->for($chapter)->create();

        // scenes.edit is matched by the `scenes.*` half of the matcher.
        $html = $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.scenes.index', $project));
    }

    public function test_the_story_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        // On a Story page the trigger swaps to nav-link's active look.
        $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->assertSee('text-nav-content border-accent', false);

        // On Home the Story trigger is inactive; the active-trigger token, whose
        // class order is unique to the trigger, must be absent.
        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('text-nav-content border-accent', false);
    }

    /**
     * Assert that the desktop dropdown trigger button labeled $label carries the
     * active class token. Triggers are <button>s (no aria-current), so the active
     * class token is the sanctioned hook. The regex ties the token to the specific
     * button by matching up to its label, so "Codex active" cannot be satisfied by
     * a different active trigger on the same page.
     */
    private function assertTriggerIsActive(string $html, string $label, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/<button[^>]*text-nav-content border-accent[^>]*>\s*'.preg_quote($label, '/').'/',
            $html,
            $message,
        );
    }

    /**
     * Assert that the trigger labeled $label is present but NOT in its active state.
     */
    private function assertTriggerIsNotActive(string $html, string $label, string $message = ''): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*text-nav-content border-accent[^>]*>\s*'.preg_quote($label, '/').'/',
            $html,
            $message,
        );
    }

    public function test_the_active_codex_type_is_marked_on_a_codex_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->getContent();

        // The enum-aware matcher must mark only the visited type, not its siblings
        // and not the Attributes item.
        $this->assertLinkIsCurrent($html, route('projects.codex.index', [$project, 'characters']));
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'locations']));
        $this->assertLinkIsNotCurrent($html, route('projects.codex-attributes.index', $project));
    }

    public function test_the_attributes_item_is_marked_and_no_type_is(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.codex-attributes.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.codex-attributes.index', $project));
        // Attributes and the codex types are distinct namespaces — no type highlights here.
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'characters']));
    }

    public function test_the_active_timeline_item_is_marked_on_a_plotlines_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.plotlines.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertLinkIsCurrent($html, route('projects.plotlines.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.events.index', $project));
    }

    public function test_the_codex_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        // On a Codex page the Codex trigger is active; the Story trigger is not.
        $codexHtml = $this->actingAs($user)
            ->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsActive($codexHtml, 'Codex');
        $this->assertTriggerIsNotActive($codexHtml, 'Story');

        // On a Story page the Codex trigger falls back to its inactive state.
        $storyHtml = $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsNotActive($storyHtml, 'Codex');
    }

    public function test_the_timeline_trigger_reflects_the_active_section(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.plotlines.index', $project))
            ->assertOk()
            ->getContent();

        $this->assertTriggerIsActive($html, 'Timeline');
        $this->assertTriggerIsNotActive($html, 'Codex');
    }

    public function test_no_dropdown_item_is_marked_on_home(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // Proves the new `active` default is false and untouched dropdowns
        // (Settings, not-yet-wired Codex/Timeline) stay unaffected. Scoped to
        // anchors so the breadcrumb's own <span aria-current> is not counted.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*aria-current="page"/', $html);
    }

    public function test_the_search_link_is_marked_on_the_search_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.search.index', $project))
            ->assertOk()
            ->getContent();

        // Search is the active top-level link; every other section stays inactive.
        $this->assertLinkIsCurrent($html, route('projects.search.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.show', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.plotlines.index', $project));
        $this->assertLinkIsNotCurrent($html, route('projects.codex.index', [$project, 'characters']));
        $this->assertLinkIsNotCurrent($html, route('projects.story.overview', $project));
    }

    public function test_the_search_link_is_present_but_not_marked_on_a_non_search_page(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->project;

        $html = $this->actingAs($user)
            ->get(route('projects.scenes.index', $project))
            ->assertOk()
            ->getContent();

        // The link is always in the nav, but only current when on a search route.
        $this->assertLinkIsNotCurrent($html, route('projects.search.index', $project));
    }

    public function test_both_menus_mark_the_same_active_item(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.revisions.index', $project))
            ->assertOk()
            ->getContent();

        $href = preg_quote(e(route('projects.revisions.index', $project)), '/');

        // Desktop: the dropdown item is the aria-current one.
        $this->assertLinkIsCurrent($html, route('projects.revisions.index', $project));

        // Responsive: the same section is highlighted in the collapsed menu.
        // Tools is the regression case — its flag used to be computed only in
        // the desktop block and read by the responsive block through PHP scope
        // leak, so reordering the file would have silently dropped this.
        // Lookaheads because attribute order in the rendered <a> is not ours.
        // `border-accent text-start` is unique to responsive-nav-link active.
        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*href="'.$href.'")(?=[^>]*border-accent text-start)[^>]*>/',
            $html,
            'Revisions should be highlighted in the responsive menu too.',
        );
    }

    public function test_the_search_link_points_at_the_project_search_route(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // The entry point resolves to the project-scoped search index.
        $this->assertStringContainsString(
            'href="'.e(route('projects.search.index', $project)).'"',
            $html,
        );
    }

    public function test_the_picker_names_the_open_project_and_offers_the_others(): void
    {
        $user = User::factory()->create();
        $open = Project::factory()->for($user)->create(['name' => 'The open one']);
        $other = Project::factory()->for($user)->create(['name' => 'Another one']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $open))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The open one', $html);
        $this->assertStringContainsString('href="'.e(route('projects.show', $other)).'"', $html);

        // "All projects" is the overflow route out of a capped list, so it is
        // part of the contract, not decoration.
        $this->assertStringContainsString('href="'.e(route('dashboard')).'"', $html);

        // x-dropdown maps only the legacy width="48" and passes anything else
        // through verbatim, so width="56" would emit a junk `56` class and leave
        // the panel unsized. Pin the rendered class, not the attribute.
        $this->assertMatchesRegularExpression('/class="absolute z-50 mt-0 w-56 /', $html);
    }

    public function test_the_picker_never_lists_another_users_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $stranger = Project::factory()->for(User::factory())->create(['name' => 'Not yours']);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        // The list is built off the signed-in user's own relation; a leak here
        // would hand out another writer's project names, not just a bad link.
        $this->assertStringNotContainsString('Not yours', $html);
        $this->assertStringNotContainsString(route('projects.show', $stranger), $html);
    }

    public function test_the_picker_caps_its_list_and_leaves_the_rest_to_all_projects(): void
    {
        $user = User::factory()->create();
        $open = Project::factory()->for($user)->create(['name' => 'Aaa open']);

        // Named so alphabetical order is predictable: the first five sortable
        // names are offered, the sixth is not.
        foreach (['Bbb', 'Ccc', 'Ddd', 'Eee', 'Fff', 'Ggg'] as $name) {
            Project::factory()->for($user)->create(['name' => $name]);
        }

        $html = $this->actingAs($user)
            ->get(route('projects.show', $open))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Bbb', $html);
        $this->assertStringContainsString('Fff', $html);
        $this->assertStringNotContainsString('Ggg', $html);
    }

    public function test_the_picker_asks_for_a_project_when_none_is_open(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Somewhere else']);

        // The dashboard is inside the app layout but belongs to no project, and
        // this user has none stored either: the trigger has nothing to name, so
        // it prompts instead of rendering blank. Factoried users have a null
        // active_project_id, which is why this case is reachable at all — the
        // test below makes the other half of that guarantee explicit.
        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Choose a project', $html);
        $this->assertStringContainsString('Somewhere else', $html);
    }

    public function test_the_menu_renders_off_route_from_the_stored_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'The stored one']);
        $user->forceFill(['active_project_id' => $project->id])->save();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        // Every "no project menu here" case above holds only because a factoried
        // user has no active project. Set it, and the menu appears off-route —
        // stated once so that behaviour stops being accidental.
        $this->assertStringContainsString('The stored one', $html);
        $this->assertStringContainsString('href="'.e(route('projects.plotlines.index', $project)).'"', $html);
        $this->assertStringNotContainsString('Choose a project', $html);
    }

    public function test_both_menus_share_one_query_for_the_picker_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Project::factory()->for($user)->create();

        // The desktop panel and the responsive menu each render the list. Without
        // memoization in ProjectNavigation that is a second identical query on
        // every authenticated page in the app.
        $pickerQueries = 0;

        DB::listen(function ($query) use (&$pickerQueries) {
            if (str_contains($query->sql, 'from "projects"') && str_contains($query->sql, 'order by "name"')) {
                $pickerQueries++;
            }
        });

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk();

        $this->assertSame(1, $pickerQueries);
    }
}
