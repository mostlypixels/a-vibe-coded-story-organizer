<?php

namespace Tests\Feature;

use App\Enums\NoteLinkType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Notable;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoteLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Project $project;

    private Note $note;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->project = Project::factory()->for($this->owner)->create();
        $this->note = Note::factory()->for($this->project)->create(['title' => 'Research']);
    }

    /** @return array<string, array{NoteLinkType}> */
    public static function linkTypes(): array
    {
        return array_combine(
            array_map(fn (NoteLinkType $case) => $case->value, NoteLinkType::cases()),
            array_map(fn (NoteLinkType $case) => [$case], NoteLinkType::cases()),
        );
    }

    /** One row of the type in the given project, named `$name`. */
    private function makeFor(NoteLinkType $type, Project $project, string $name = 'Target'): Model
    {
        $book = $project->books()->first();
        $chapter = fn (array $attributes = []) => Chapter::factory()->for(Act::factory()->for($book)->create())->create($attributes);

        return match ($type) {
            NoteLinkType::Book => tap($book)->update(['name' => $name]),
            NoteLinkType::Act => Act::factory()->for($book)->create(['name' => $name]),
            NoteLinkType::Chapter => $chapter(['name' => $name]),
            NoteLinkType::Scene => Scene::factory()->for($chapter())->create(['name' => $name]),
            NoteLinkType::Event => Event::factory()->for($project)->create(['title' => $name]),
            NoteLinkType::Plotline => Plotline::factory()->for($project)->create(['name' => $name]),
            NoteLinkType::Codex => CodexEntry::factory()->for($project)->create(['name' => $name]),
        };
    }

    private function linkCount(): int
    {
        return Notable::query()->where('note_id', $this->note->id)->count();
    }

    // --- Store and destroy -------------------------------------------------

    #[DataProvider('linkTypes')]
    public function test_owner_links_and_unlinks_each_type(NoteLinkType $type): void
    {
        $target = $this->makeFor($type, $this->project);

        $this->actingAs($this->owner)
            ->from(route('notes.edit', $this->note))
            ->post(route('notes.links.store', $this->note), ['type' => $type->value, 'id' => $target->getKey()])
            ->assertRedirect(route('notes.edit', $this->note));

        $this->assertTrue($this->note->{$type->noteRelation()}()->whereKey($target->getKey())->exists());

        $this->actingAs($this->owner)
            ->deleteJson(route('notes.links.destroy', ['note' => $this->note, 'type' => $type->value, 'id' => $target->getKey()]))
            ->assertNoContent();

        $this->assertSame(0, $this->linkCount());
    }

    #[DataProvider('linkTypes')]
    public function test_a_target_from_another_project_is_a_validation_error(NoteLinkType $type): void
    {
        $foreign = $this->makeFor($type, Project::factory()->for($this->other)->create());

        $this->actingAs($this->owner)
            ->postJson(route('notes.links.store', $this->note), ['type' => $type->value, 'id' => $foreign->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');

        $this->assertSame(0, $this->linkCount());
    }

    public function test_an_unknown_type_or_missing_id_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('notes.links.store', $this->note), ['type' => 'tag', 'id' => 1])
            ->assertJsonValidationErrors('type');

        $this->actingAs($this->owner)
            ->postJson(route('notes.links.store', $this->note), ['type' => 'scene'])
            ->assertJsonValidationErrors('id');
    }

    public function test_storing_an_existing_link_is_a_no_op(): void
    {
        $scene = $this->makeFor(NoteLinkType::Scene, $this->project);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->owner)
                ->postJson(route('notes.links.store', $this->note), ['type' => 'scene', 'id' => $scene->getKey()])
                ->assertNoContent();
        }

        $this->assertSame(1, $this->linkCount());
    }

    public function test_the_destroy_route_rejects_an_unknown_type(): void
    {
        $this->actingAs($this->owner)
            ->delete('/notes/'.$this->note->id.'/links/tag/1')
            ->assertNotFound();
    }

    public function test_a_non_owner_cannot_link_or_unlink(): void
    {
        $scene = $this->makeFor(NoteLinkType::Scene, $this->project);
        $this->note->linkTo($scene);

        $this->actingAs($this->other)
            ->post(route('notes.links.store', $this->note), ['type' => 'scene', 'id' => $scene->getKey()])
            ->assertForbidden();

        $this->actingAs($this->other)
            ->delete(route('notes.links.destroy', ['note' => $this->note, 'type' => 'scene', 'id' => $scene->getKey()]))
            ->assertForbidden();

        $this->assertSame(1, $this->linkCount());
    }

    // --- Candidates ---------------------------------------------------------

    #[DataProvider('linkTypes')]
    public function test_link_candidates_return_only_this_projects_rows(NoteLinkType $type): void
    {
        $mine = $this->makeFor($type, $this->project, 'Melusine');
        $this->makeFor($type, Project::factory()->for($this->other)->create(), 'Melusine abroad');

        $this->actingAs($this->owner)
            ->getJson(route('notes.link-candidates', ['note' => $this->note, 'type' => $type->value, 'q' => 'Melu']))
            ->assertOk()
            ->assertExactJson([['type' => $type->value, 'id' => $mine->getKey(), 'label' => 'Melusine']]);
    }

    public function test_link_candidates_need_a_valid_type(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('notes.link-candidates', ['note' => $this->note, 'type' => 'tag']))
            ->assertJsonValidationErrors('type');
    }

    public function test_note_candidates_return_only_this_projects_notes(): void
    {
        $match = Note::factory()->for($this->project)->create(['title' => 'Castle research']);
        Note::factory()->for(Project::factory()->for($this->other)->create())->create(['title' => 'Castle abroad']);

        $this->actingAs($this->owner)
            ->getJson(route('notes.candidates', ['project' => $this->project, 'q' => 'Castle']))
            ->assertOk()
            ->assertExactJson([['id' => $match->id, 'title' => 'Castle research']]);
    }

    public function test_a_non_owner_cannot_read_candidates(): void
    {
        $this->actingAs($this->other)
            ->getJson(route('notes.link-candidates', ['note' => $this->note, 'type' => 'scene']))
            ->assertForbidden();

        $this->actingAs($this->other)
            ->getJson(route('notes.candidates', $this->project))
            ->assertForbidden();
    }

    // --- Pages ---------------------------------------------------------------

    public function test_read_and_edit_pages_list_the_links(): void
    {
        $scene = $this->makeFor(NoteLinkType::Scene, $this->project, 'The fountain');
        $this->note->linkTo($scene);

        $this->actingAs($this->owner)->get(route('notes.show', $this->note))
            ->assertOk()
            ->assertSee('Linked to')
            ->assertSee('The fountain')
            ->assertSee(route('scenes.show', $scene), false);

        $this->actingAs($this->owner)->get(route('notes.edit', $this->note))
            ->assertOk()
            ->assertSee('The fountain')
            ->assertSee(Js::from(route('notes.links.destroy', ['note' => $this->note, 'type' => 'scene', 'id' => $scene->getKey()]))->toHtml(), false);
    }
}
