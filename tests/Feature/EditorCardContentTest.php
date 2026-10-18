<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\AttributeTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The HTML partials the Write page fetches for a codex card and a note card. */
class EditorCardContentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private CodexEntry $entry;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->entry = CodexEntry::factory()->for($this->project)->character()->create([
            'name' => 'Melusine',
            'description' => '<p>A river spirit.</p>',
        ]);
        $attribute = CodexAttribute::factory()->for($this->project)->appliesTo(CodexEntryType::Character)->create(['name' => 'Hair color']);

        $start = $this->project->events()->where('title', 'Start')->firstOrFail();
        $this->event = Event::factory()->for($this->project)->create(['event_datetime' => '2020-11-15 00:00:00']);

        $timeline = new AttributeTimeline($this->entry, $attribute);
        $timeline->upsertAt($start, 'blonde');
        $timeline->upsertAt($this->event, 'black');
    }

    private function sceneIn(Project $project, ?Event $event): Scene
    {
        $chapter = Chapter::factory()->for(Act::factory()->for($project->books()->firstOrFail()))->create();

        return Scene::factory()->for($chapter)->create(['event_id' => $event?->id]);
    }

    public function test_codex_card_shows_values_as_of_the_scene_event(): void
    {
        $scene = $this->sceneIn($this->project, $this->event);

        $this->actingAs($this->user)
            ->get(route('codex.card', [$this->entry, 'scene' => $scene->id]))
            ->assertOk()
            ->assertSee('Character')
            ->assertSee('A river spirit.')
            ->assertSee('Hair color')
            ->assertSee('black')
            ->assertDontSee('blonde')
            ->assertDontSee('<html', false);
    }

    public function test_codex_card_shows_the_undetermined_state_for_an_unassigned_scene(): void
    {
        $scene = $this->sceneIn($this->project, null);

        $this->actingAs($this->user)
            ->get(route('codex.card', [$this->entry, 'scene' => $scene->id]))
            ->assertOk()
            ->assertSee('A river spirit.')
            ->assertSee('Assign an event to this scene to see codex values.')
            ->assertDontSee('Hair color');
    }

    public function test_codex_card_without_a_scene_shows_the_undetermined_state(): void
    {
        $this->actingAs($this->user)
            ->get(route('codex.card', $this->entry))
            ->assertOk()
            ->assertSee('Assign an event to this scene to see codex values.');
    }

    public function test_codex_card_says_when_the_entry_is_not_present_at_the_moment(): void
    {
        $inception = Event::factory()->for($this->project)->create(['event_datetime' => '2030-01-01']);
        $this->entry->update(['inception_event_id' => $inception->id]);
        $scene = $this->sceneIn($this->project, $this->event);

        $this->actingAs($this->user)
            ->get(route('codex.card', [$this->entry, 'scene' => $scene->id]))
            ->assertOk()
            ->assertSee('Not present at this scene', false)
            ->assertSee('A river spirit.')
            ->assertDontSee('Hair color');
    }

    public function test_codex_card_404s_for_a_scene_of_another_project(): void
    {
        $otherProject = Project::factory()->for($this->user)->create();
        $foreignScene = $this->sceneIn($otherProject, null);

        $this->actingAs($this->user)
            ->get(route('codex.card', [$this->entry, 'scene' => $foreignScene->id]))
            ->assertNotFound();
    }

    public function test_note_card_shows_the_category_and_a_resolved_entity_link(): void
    {
        $category = NoteCategory::factory()->for($this->project)->create(['name' => 'Research']);
        $note = Note::factory()->for($this->project)->create([
            'note_category_id' => $category->id,
            'body' => '<p>See <a href="entity:codex/'.$this->entry->id.'">her</a>.</p>',
        ]);

        $this->actingAs($this->user)
            ->get(route('notes.card', $note))
            ->assertOk()
            ->assertSee('Research')
            ->assertSee('href="'.route('codex.show', $this->entry).'"', false)
            ->assertDontSee('entity:codex', false)
            ->assertDontSee('<html', false);
    }

    public function test_a_non_owner_gets_403_on_both_cards(): void
    {
        $note = Note::factory()->for($this->project)->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('codex.card', $this->entry))->assertForbidden();
        $this->actingAs($other)->get(route('notes.card', $note))->assertForbidden();
    }
}
