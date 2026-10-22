<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Enums\EditorCardState;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\EditorCard;
use App\Models\Event;
use App\Models\Note;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** The per-user, per-scene card state of the Write page. */
class EditorCardTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Project $project): Scene
    {
        $act = Act::factory()->for($project->books()->first())->create();

        return Scene::factory()->for(Chapter::factory()->for($act))->create();
    }

    /** @return array{User, Project, Scene} */
    private function ownedScene(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        return [$user, $project, $this->sceneIn($project)];
    }

    /** @return array<string, mixed> */
    private function payload(string $type, int $id, string $state = 'expanded'): array
    {
        return ['type' => $type, 'id' => $id, 'state' => $state];
    }

    public function test_put_creates_then_updates_the_same_row(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id))
            ->assertNoContent();
        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id, 'collapsed'))
            ->assertNoContent();

        $this->assertSame(1, EditorCard::count());
        $card = EditorCard::first();
        $this->assertSame($user->id, $card->user_id);
        $this->assertSame($scene->id, $card->scene_id);
        $this->assertSame($entry->id, $card->codex_entry_id);
        $this->assertNull($card->note_id);
        $this->assertSame(EditorCardState::Collapsed, $card->state);
    }

    public function test_a_note_card_saves_with_a_null_codex_entry(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $note = Note::factory()->for($project)->create();

        foreach (['expanded', 'closed'] as $state) {
            $this->actingAs($user)
                ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('note', $note->id, $state))
                ->assertNoContent();
        }

        $this->assertSame(1, EditorCard::count());
        $card = EditorCard::first();
        $this->assertSame($note->id, $card->note_id);
        $this->assertNull($card->codex_entry_id);
        $this->assertSame(EditorCardState::Closed, $card->state);
    }

    public function test_each_state_value_saves(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        foreach (EditorCardState::cases() as $state) {
            $this->actingAs($user)
                ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id, $state->value))
                ->assertNoContent();

            $this->assertSame($state, EditorCard::first()->state);
        }
    }

    public function test_a_bad_state_is_rejected(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id, 'hidden'))
            ->assertJsonValidationErrors('state');

        $this->assertSame(0, EditorCard::count());
    }

    public function test_a_missing_or_unsupported_target_is_rejected(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), ['state' => 'expanded'])
            ->assertJsonValidationErrors(['type', 'id']);
        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('scene', $scene->id))
            ->assertJsonValidationErrors('type');

        $this->assertSame(0, EditorCard::count());
    }

    public function test_the_model_refuses_a_row_with_both_or_neither_target(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $note = Note::factory()->for($project)->create();

        foreach ([[null, null], [$entry->id, $note->id]] as [$entryId, $noteId]) {
            try {
                EditorCard::create([
                    'user_id' => $user->id,
                    'scene_id' => $scene->id,
                    'codex_entry_id' => $entryId,
                    'note_id' => $noteId,
                    'state' => EditorCardState::Expanded,
                ]);
                $this->fail('The model accepted an invalid card.');
            } catch (LogicException) {
                $this->assertSame(0, EditorCard::count());
            }
        }
    }

    public function test_a_codex_entry_of_another_project_is_not_found(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $foreign = CodexEntry::factory()->for(Project::factory()->for($user))->character()->create();

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $foreign->id))
            ->assertNotFound();

        $this->assertSame(0, EditorCard::count());
    }

    public function test_a_note_of_another_project_is_not_found(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $foreign = Note::factory()->for(Project::factory()->for($user))->create();

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('note', $foreign->id))
            ->assertNotFound();

        $this->assertSame(0, EditorCard::count());
    }

    public function test_a_non_owner_gets_403(): void
    {
        [, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs(User::factory()->create())
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id))
            ->assertForbidden();

        $this->assertSame(0, EditorCard::count());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        [, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->put(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id))
            ->assertRedirect(route('login'));
    }

    public function test_deleting_the_scene_the_entry_or_the_note_removes_its_rows(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $otherScene = $this->sceneIn($project);
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $note = Note::factory()->for($project)->create();

        EditorCard::saveState($user, $scene, $entry, EditorCardState::Expanded);
        EditorCard::saveState($user, $otherScene, $entry, EditorCardState::Expanded);
        EditorCard::saveState($user, $otherScene, $note, EditorCardState::Collapsed);

        $scene->delete();
        $this->assertSame(2, EditorCard::count());

        $note->delete();
        $this->assertSame(1, EditorCard::count());

        $entry->delete();
        $this->assertSame(0, EditorCard::count());
    }

    public function test_rows_are_per_user(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $other = User::factory()->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        EditorCard::saveState($other, $scene, $entry, EditorCardState::Closed);

        $this->actingAs($user)
            ->putJson(route('scenes.editor-cards.update', $scene), $this->payload('codex', $entry->id, 'expanded'))
            ->assertNoContent();

        $this->assertSame(2, EditorCard::count());
        $this->assertSame(EditorCardState::Closed, EditorCard::where('user_id', $other->id)->first()->state);
        $this->assertSame(EditorCardState::Expanded, EditorCard::where('user_id', $user->id)->first()->state);
    }

    public function test_the_helper_lists_one_users_cards_for_one_scene_newest_first(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $first = CodexEntry::factory()->for($project)->character()->create();
        $second = Note::factory()->for($project)->create();
        $third = CodexEntry::factory()->for($project)->character()->create();

        EditorCard::saveState($user, $scene, $first, EditorCardState::Expanded);
        EditorCard::saveState($user, $scene, $second, EditorCardState::Expanded);
        EditorCard::saveState($user, $this->sceneIn($project), $third, EditorCardState::Expanded);
        EditorCard::saveState(User::factory()->create(), $scene, $third, EditorCardState::Expanded);
        $this->travel(1)->minutes();
        EditorCard::saveState($user, $scene, $first, EditorCardState::Collapsed);

        $cards = EditorCard::forUserAndScene($user, $scene)->get();

        $this->assertCount(2, $cards);
        $this->assertSame($first->id, $cards[0]->codex_entry_id);
        $this->assertSame($second->id, $cards[1]->note_id);
    }

    /** @return list<int> */
    private function cardIds(string $html): array
    {
        preg_match_all('/data-card-id="(\d+)"/', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    public function test_the_notes_section_lists_saved_and_linked_notes_and_hides_closed_ones(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $saved = Note::factory()->for($project)->create();
        $closed = Note::factory()->for($project)->create();
        $closed->linkTo($scene);
        EditorCard::saveState($user, $scene, $saved, EditorCardState::Expanded);
        EditorCard::saveState($user, $scene, $closed, EditorCardState::Closed);

        $response = $this->actingAs($user)->get(route('scenes.editor-cards.index', [$scene, 'note']));
        $this->assertSame([$saved->id], $this->cardIds($response->assertOk()->getContent()));

        // A note linked after the page load shows up, collapsed.
        $linked = Note::factory()->for($project)->create();
        $linked->linkTo($scene);

        $html = $this->get(route('scenes.editor-cards.index', [$scene, 'note']))->assertOk()->getContent();

        $this->assertSame([$saved->id, $linked->id], $this->cardIds($html));
        $this->assertStringContainsString('data-card-id="'.$linked->id.'"', $html);
        $this->assertMatchesRegularExpression('/data-card-id="'.$linked->id.'"\s+data-state="collapsed"/', $html);
    }

    public function test_the_codex_section_shows_values_as_of_the_scene_event(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $event = Event::factory()->for($project)->create();
        $scene->update(['event_id' => $event->id]);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        EditorCard::saveState($user, $scene, $entry, EditorCardState::Expanded);

        $url = route('scenes.editor-cards.index', [$scene->fresh(), 'codex']);
        $before = $this->actingAs($user)->get($url)->assertOk()->getContent();
        $this->assertSame([$entry->id], $this->cardIds($before));
        $this->assertStringNotContainsString('Marked by a scar', $before);

        CodexAttributeValue::factory()->startingAt($event)->create([
            'codex_entry_id' => $entry->id,
            'codex_attribute_id' => $attribute->id,
            'value' => 'Marked by a scar',
        ]);

        $this->get($url)->assertOk()->assertSee('Marked by a scar');
    }

    public function test_a_section_holds_no_card_of_the_other_type(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $note = Note::factory()->for($project)->create();
        EditorCard::saveState($user, $scene, $entry, EditorCardState::Expanded);
        EditorCard::saveState($user, $scene, $note, EditorCardState::Expanded);

        $this->actingAs($user);

        $this->assertSame([$entry->id], $this->cardIds($this->get(route('scenes.editor-cards.index', [$scene, 'codex']))->getContent()));
        $this->assertSame([$note->id], $this->cardIds($this->get(route('scenes.editor-cards.index', [$scene, 'note']))->getContent()));
    }

    public function test_the_section_endpoint_gives_403_to_a_non_owner_for_both_types(): void
    {
        [, , $scene] = $this->ownedScene();
        $this->actingAs(User::factory()->create());

        $this->get(route('scenes.editor-cards.index', [$scene, 'codex']))->assertForbidden();
        $this->get(route('scenes.editor-cards.index', [$scene, 'note']))->assertForbidden();
    }

    public function test_the_section_endpoint_redirects_a_guest_to_login(): void
    {
        [, , $scene] = $this->ownedScene();

        $this->get(route('scenes.editor-cards.index', [$scene, 'note']))->assertRedirect(route('login'));
    }

    public function test_an_unknown_section_type_is_not_found(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get('/scenes/'.$scene->id.'/editor-cards/scene')
            ->assertNotFound();
    }

    public function test_the_section_endpoint_only_reads(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        Note::factory()->for($project)->create()->linkTo($scene);

        $this->actingAs($user)->get(route('scenes.editor-cards.index', [$scene, 'note']))->assertOk();

        $this->assertSame(0, EditorCard::count());
    }

    public function test_the_endpoint_renders_the_same_cards_as_the_page_load(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $event = Event::factory()->for($project)->create();
        $scene->update(['event_id' => $event->id]);
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        CodexAttributeValue::factory()->startingAt($event)->create(['codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id]);
        $note = Note::factory()->for($project)->create();
        $linked = Note::factory()->for($project)->create();
        $linked->linkTo($scene);
        EditorCard::saveState($user, $scene, $entry, EditorCardState::Expanded);
        EditorCard::saveState($user, $scene, $note, EditorCardState::Collapsed);

        $this->actingAs($user);
        $page = $this->get(route('scenes.write', $scene->fresh()))->assertOk()->getContent();
        preg_match_all('/data-editor-cards>(.*?)<template x-ref="template">/s', $page, $sections);
        $this->assertCount(2, $sections[1]);

        foreach (['codex' => 0, 'note' => 1] as $type => $index) {
            $endpoint = $this->get(route('scenes.editor-cards.index', [$scene->fresh(), $type]))->getContent();
            $normalize = fn (string $html) => trim(preg_replace('/\s+/', ' ', preg_replace('/<\/div>\s*$/', '', trim($html))));

            $this->assertNotSame('', trim(strip_tags($endpoint)));
            $this->assertStringContainsString($normalize($endpoint), $normalize($sections[1][$index]));
        }
    }
}
