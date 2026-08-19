<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Book;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bind each sidebar create action to its page form. */
class CreateActionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> Route URL => the id of the form its Create button drives. */
    private function createPages(Project $project, Book $book): array
    {
        return [
            route('projects.create') => 'project-create-form',
            route('books.acts.create', $book) => 'act-create-form',
            route('books.chapters.create', $book) => 'chapter-create-form',
            route('books.scenes.create', $book) => 'scene-create-form',
            route('projects.events.create', $project) => 'event-create-form',
            route('projects.plotlines.create', $project) => 'plotline-create-form',
            route('projects.codex-attributes.create', $project) => 'codex-attribute-create-form',
            route('projects.codex.create', [$project, CodexEntryType::Character->routeKey()]) => 'codex-entry-create-form',
        ];
    }

    public function test_every_create_page_puts_its_submit_in_an_actions_card_bound_to_its_form(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        foreach ($this->createPages($project, $book) as $url => $formId) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="'.$formId.'"', $html, $url.' has no form to submit.');
            $this->assertStringContainsString('form="'.$formId.'"', $html, $url.' has no button bound to its form.');
            $this->assertStringContainsString(__('Actions'), $html, $url.' has no Actions card.');
            $this->assertStringContainsString(__('Cancel'), $html, $url.' has no way back to the list.');
        }
    }
}
