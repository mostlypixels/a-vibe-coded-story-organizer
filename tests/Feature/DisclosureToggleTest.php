<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * A toggle button must tell a screen reader if its panel is open.
 * Each toggle carries `aria-expanded` and an `aria-controls` that points to its panel.
 */
class DisclosureToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dropdown_trigger_names_its_panel_and_starts_collapsed(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-dropdown>
                <x-slot name="trigger"><x-disclosure-button>Menu</x-disclosure-button></x-slot>
                <x-slot name="content"><a href="/x">Item</a></x-slot>
            </x-dropdown>
            BLADE);

        $toggles = $this->assertTogglesControlExistingPanels($html);

        $this->assertCount(1, $toggles);
        $this->assertSame('false', $toggles[0]->getAttribute('aria-expanded'));
        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
    }

    public function test_two_dropdowns_on_one_page_get_different_panel_ids(): void
    {
        $html = Blade::render(<<<'BLADE'
            @foreach ([1, 2] as $i)
                <x-dropdown>
                    <x-slot name="trigger"><x-disclosure-button>Menu</x-disclosure-button></x-slot>
                    <x-slot name="content"><a href="/x">Item</a></x-slot>
                </x-dropdown>
            @endforeach
            BLADE);

        $toggles = $this->assertTogglesControlExistingPanels($html);

        $this->assertCount(2, $toggles);
        $this->assertNotSame(
            $toggles[0]->getAttribute('aria-controls'),
            $toggles[1]->getAttribute('aria-controls'),
        );
    }

    public function test_a_popover_trigger_names_its_panel_and_starts_collapsed(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-popover>
                <x-slot name="trigger"><x-disclosure-button>Help</x-disclosure-button></x-slot>
                <x-slot name="content">Some help.</x-slot>
            </x-popover>
            BLADE);

        $toggles = $this->assertTogglesControlExistingPanels($html);

        $this->assertCount(1, $toggles);
        $this->assertSame('false', $toggles[0]->getAttribute('aria-expanded'));
    }

    public function test_the_app_navigation_toggles_name_their_panels(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        $toggles = $this->assertTogglesControlExistingPanels($html);

        // The project picker, the Story, Timeline, Codex and Tools menus, the user menu and the mobile menu.
        $this->assertGreaterThanOrEqual(7, count($toggles));

        $mobile = $this->document($html)->querySelector('[aria-controls="mobile-navigation"]');
        $this->assertNotNull($mobile, 'The mobile menu button does not name its panel.');
        $this->assertSame('false', $mobile->getAttribute('aria-expanded'));
        $this->assertNotSame('', trim($mobile->getAttribute('aria-label') ?? ''));
    }

    public function test_the_error_page_navigation_toggles_name_their_panels(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        $html = $this->actingAs($user)->get('/this-page-does-not-exist')->assertNotFound()->getContent();

        // The project picker and the user menu.
        $this->assertCount(2, $this->assertTogglesControlExistingPanels($html));
    }

    public function test_each_scene_toggle_on_the_story_overview_names_its_prose(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $book = $project->books()->first();
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scenes = Scene::factory()->count(2)->for($chapter)->create();

        $html = $this->actingAs($user)->get(route('books.story.overview', $book))->assertOk()->getContent();

        $this->assertTogglesControlExistingPanels($html);

        foreach ($scenes as $scene) {
            $toggle = $this->document($html)->querySelector('[aria-controls="scene-'.$scene->id.'-prose"]');
            $this->assertNotNull($toggle, "The toggle of scene {$scene->id} does not name its prose.");
            // Scene prose starts open on this page.
            $this->assertSame('true', $toggle->getAttribute('aria-expanded'));
        }
    }

    public function test_the_shared_scene_description_toggle_names_its_panel(): void
    {
        $scene = Scene::factory()->create(['description' => 'A short summary.']);
        $scene->forceFill(['share_token' => 'live-token', 'share_expires_at' => now()->addDay()])->save();

        $html = $this->get(route('shared.scenes.show', 'live-token'))->assertOk()->getContent();

        $toggles = $this->assertTogglesControlExistingPanels($html);

        $this->assertCount(1, $toggles);
        $this->assertSame('false', $toggles[0]->getAttribute('aria-expanded'));
    }

    public function test_the_wysiwyg_toolbar_menus_name_their_panels(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->first()))->create();
        $scene = Scene::factory()->for($chapter)->create();

        $html = $this->actingAs($user)->get(route('scenes.edit', $scene))->assertOk()->getContent();

        $toolbar = $this->document($html)->querySelectorAll('[role="toolbar"] [aria-controls]');
        $this->assertGreaterThan(0, $toolbar->length, 'The toolbar menus do not name their panels.');

        // The buttons inside a menu are not toggles.
        foreach ($toolbar as $toggle) {
            $this->assertSame('open.toString()', $toggle->getAttribute(':aria-expanded'));
            $panel = $this->document($html)->getElementById($toggle->getAttribute('aria-controls'));
            $this->assertSame(0, $panel->querySelectorAll('[aria-controls]')->length);
        }

        $this->assertTogglesControlExistingPanels($html);
    }

    public function test_the_revision_sidebar_groups_name_their_lists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project->books()->first())->create();
        Revision::factory()->create([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $project->id,
            'field' => 'description',
        ]);

        $html = $this->actingAs($user)
            ->get(route('revisions.index', ['entity' => 'act', 'id' => $act->id]))
            ->assertOk()
            ->getContent();

        $groups = $this->document($html)->querySelectorAll('nav[aria-label="Revision history"] button[aria-expanded]');
        $this->assertGreaterThan(0, $groups->length);

        foreach ($groups as $group) {
            $this->assertNotSame('', (string) $group->getAttribute('aria-controls'), 'A revision group does not name its list.');
        }

        $this->assertTogglesControlExistingPanels($html);
    }

    /**
     * Assert each `aria-controls` points to exactly one element and sits on a button with a
     * static `aria-expanded`. The static value is the state before Alpine starts.
     *
     * @return list<Element>
     */
    private function assertTogglesControlExistingPanels(string $html): array
    {
        $document = $this->document($html);
        $toggles = iterator_to_array($document->querySelectorAll('[aria-controls]'));

        foreach ($toggles as $toggle) {
            $id = $toggle->getAttribute('aria-controls');

            $this->assertSame('BUTTON', $toggle->tagName, "The toggle for #{$id} is not a button.");
            $this->assertContains($toggle->getAttribute('aria-expanded'), ['true', 'false'], "The toggle for #{$id} has no aria-expanded.");
            $this->assertCount(1, $document->querySelectorAll('[id="'.$id.'"]'), "#{$id} must exist exactly once.");
        }

        return array_values($toggles);
    }

    private function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }
}
