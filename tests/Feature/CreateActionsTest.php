<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every create page carries the same sidebar Actions card as its edit page, so the
 * primary action sits in one place across the app.
 *
 * The card renders outside the <form> tag (it is in the sidebar column) and reaches
 * the form through the HTML `form` attribute. That attribute is silent when it points
 * at an id no form has: the button then belongs to no form and does nothing. This
 * pins both ends of the wiring for every create page at once.
 */
class CreateActionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> Route URL => the id of the form its Create button drives. */
    private function createPages(Project $project): array
    {
        return [
            route('projects.create') => 'project-create-form',
            route('projects.acts.create', $project) => 'act-create-form',
            route('projects.chapters.create', $project) => 'chapter-create-form',
            route('projects.scenes.create', $project) => 'scene-create-form',
            route('projects.events.create', $project) => 'event-create-form',
            route('projects.plotlines.create', $project) => 'plotline-create-form',
            route('projects.codex-attributes.create', $project) => 'codex-attribute-create-form',
            route('projects.codex.create', [$project, CodexEntryType::Character->routeKey()]) => 'codex-entry-create-form',
        ];
    }

    public function test_every_create_page_puts_its_submit_in_an_actions_card_bound_to_its_form(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        foreach ($this->createPages($project) as $url => $formId) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="'.$formId.'"', $html, $url.' has no form to submit.');
            $this->assertStringContainsString('form="'.$formId.'"', $html, $url.' has no button bound to its form.');
            $this->assertStringContainsString(__('Actions'), $html, $url.' has no Actions card.');
            $this->assertStringContainsString(__('Cancel'), $html, $url.' has no way back to the list.');
        }
    }
}
