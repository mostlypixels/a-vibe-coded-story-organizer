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
            ->assertSee('text-white border-flame-500', false);

        // On Home the Story trigger is inactive; the active-trigger token, whose
        // class order is unique to the trigger, must be absent.
        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('text-white border-flame-500', false);
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
            '/<button[^>]*text-white border-flame-500[^>]*>\s*'.preg_quote($label, '/').'/',
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
            '/<button[^>]*text-white border-flame-500[^>]*>\s*'.preg_quote($label, '/').'/',
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
}
