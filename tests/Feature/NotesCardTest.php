<?php

namespace Tests\Feature;

use App\Enums\NoteLinkType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotesCardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::factory()->for($this->owner)->create();
    }

    /** @return array<string, array{NoteLinkType, string, string}> */
    public static function linkableTypes(): array
    {
        $routes = [
            'book' => [NoteLinkType::Book, 'books.show', 'books.edit'],
            'act' => [NoteLinkType::Act, 'acts.show', 'acts.edit'],
            'chapter' => [NoteLinkType::Chapter, 'chapters.show', 'chapters.edit'],
            'scene' => [NoteLinkType::Scene, 'scenes.show', 'scenes.edit'],
            'event' => [NoteLinkType::Event, 'events.show', 'events.edit'],
            'plotline' => [NoteLinkType::Plotline, 'plotlines.show', 'plotlines.edit'],
            'codex' => [NoteLinkType::Codex, 'codex.show', 'codex.edit'],
        ];

        return $routes;
    }

    private function makeFor(NoteLinkType $type): Model
    {
        $book = $this->project->books()->first();
        $act = fn () => Act::factory()->for($book)->create();
        $chapter = fn () => Chapter::factory()->for($act())->create();

        return match ($type) {
            NoteLinkType::Book => $book,
            NoteLinkType::Act => $act(),
            NoteLinkType::Chapter => $chapter(),
            NoteLinkType::Scene => Scene::factory()->for($chapter())->create(),
            NoteLinkType::Event => Event::factory()->for($this->project)->create(),
            NoteLinkType::Plotline => Plotline::factory()->for($this->project)->create(),
            NoteLinkType::Codex => CodexEntry::factory()->for($this->project)->create(),
        };
    }

    private function noteFor(string $title): Note
    {
        return Note::factory()->for($this->project)->create(['title' => $title, 'body' => '<p>Body of '.$title.'</p>']);
    }

    #[DataProvider('linkableTypes')]
    public function test_read_and_edit_pages_list_linked_notes_only(NoteLinkType $type, string $showRoute, string $editRoute): void
    {
        $entity = $this->makeFor($type);
        $linked = $this->noteFor('Linked fountain note');
        $linked->linkTo($entity);
        $this->noteFor('Unlinked harbour note');
        $unlinkUrl = route('notes.links.destroy', ['note' => $linked, 'type' => $type->value, 'id' => $entity->getKey()]);

        foreach ([$showRoute, $editRoute] as $route) {
            $this->actingAs($this->owner)
                ->get(route($route, $entity))
                ->assertOk()
                ->assertSee('Linked fountain note')
                ->assertSee('Body of Linked fountain note')
                ->assertDontSee('Unlinked harbour note')
                ->assertSee(e(Js::from($unlinkUrl)), false);
        }
    }

    #[DataProvider('linkableTypes')]
    public function test_the_card_offers_new_note_and_the_link_picker(NoteLinkType $type, string $showRoute, string $editRoute): void
    {
        $entity = $this->makeFor($type);

        foreach ([$showRoute, $editRoute] as $route) {
            $this->actingAs($this->owner)
                ->get(route($route, $entity))
                ->assertOk()
                ->assertSee('No notes are linked here yet.')
                ->assertSee(route('projects.notes.create', ['project' => $this->project, 'link' => $type->value.':'.$entity->getKey()]), false)
                ->assertSee('Link a note')
                ->assertSee(Js::from(route('notes.candidates', $this->project)), false)
                ->assertSee('linkType: '.Js::from($type->value), false);
        }
    }

    public function test_the_card_lists_notes_by_title(): void
    {
        $plotline = $this->makeFor(NoteLinkType::Plotline);
        $this->noteFor('Zebra')->linkTo($plotline);
        $this->noteFor('apple')->linkTo($plotline);

        $this->actingAs($this->owner)
            ->get(route('plotlines.show', $plotline))
            ->assertSeeInOrder(['apple', 'Zebra']);
    }

    public function test_a_page_runs_the_same_queries_for_one_note_or_many(): void
    {
        $plotline = $this->makeFor(NoteLinkType::Plotline);
        $this->noteFor('First')->linkTo($plotline);

        $countQueries = function () use ($plotline): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('plotlines.show', $plotline))->assertOk();

            return count(DB::getQueryLog());
        };

        // The first request pays one-off queries (settings, session). Warm up first.
        $countQueries();
        $withOne = $countQueries();

        foreach (['Second', 'Third', 'Fourth', 'Fifth'] as $title) {
            $this->noteFor($title)->linkTo($plotline);
        }

        $many = $countQueries();
        $this->assertSame($withOne, $many);
    }

    public function test_a_scene_page_lists_the_linked_note(): void
    {
        $scene = $this->makeFor(NoteLinkType::Scene);
        $this->noteFor('Fresh linked note')->linkTo($scene);

        foreach (['scenes.show', 'scenes.edit'] as $route) {
            $this->actingAs($this->owner)
                ->get(route($route, $scene))
                ->assertOk()
                ->assertSee('Fresh linked note');
        }
    }

    public function test_a_non_owner_cannot_open_the_pages_that_carry_the_card(): void
    {
        $other = User::factory()->create();
        $plotline = $this->makeFor(NoteLinkType::Plotline);
        $this->noteFor('Secret')->linkTo($plotline);

        foreach (['plotlines.show', 'plotlines.edit'] as $route) {
            $this->actingAs($other)->get(route($route, $plotline))->assertForbidden();
        }
    }
}
