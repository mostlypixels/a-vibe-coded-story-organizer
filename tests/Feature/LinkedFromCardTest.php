<?php

namespace Tests\Feature;

use App\Enums\LinkTargetType;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkedFromCardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        [$this->project] = $this->projectWithBook($this->owner);
    }

    /** @return array<string, array{LinkTargetType}> */
    public static function targetTypes(): array
    {
        $types = [];

        foreach (LinkTargetType::cases() as $type) {
            $types[$type->value] = [$type];
        }

        return $types;
    }

    private function makeFor(LinkTargetType $type, ?Project $project = null): Model
    {
        $project ??= $this->project;
        $book = $project->books()->first();
        $act = fn () => Act::factory()->for($book)->create();
        $chapter = fn () => Chapter::factory()->for($act())->create();

        return match ($type) {
            LinkTargetType::Book => $book,
            LinkTargetType::Act => $act(),
            LinkTargetType::Chapter => $chapter(),
            LinkTargetType::Scene => Scene::factory()->for($chapter())->create(),
            LinkTargetType::Event => Event::factory()->for($project)->create(),
            LinkTargetType::Plotline => Plotline::factory()->for($project)->create(),
            LinkTargetType::Codex => CodexEntry::factory()->for($project)->create(),
            LinkTargetType::Note => Note::factory()->for($project)->create(),
        };
    }

    private function link(LinkTargetType $type, Model $target): string
    {
        return '<p><a href="entity:'.$type->value.'/'.$target->getKey().'">go</a></p>';
    }

    private function showUrl(LinkTargetType $type, Model $target): string
    {
        return route($type->showRoute(), $target);
    }

    #[DataProvider('targetTypes')]
    public function test_the_card_lists_a_source_with_a_working_link(LinkTargetType $type): void
    {
        $target = $this->makeFor($type);
        $source = Note::factory()->for($this->project)->create([
            'title' => 'Harbour research',
            'body' => $this->link($type, $target),
        ]);

        $this->actingAs($this->owner)
            ->get($this->showUrl($type, $target))
            ->assertOk()
            ->assertSee('Linked from')
            ->assertSee('data-linked-from', false)
            ->assertSee('Harbour research')
            ->assertSee('href="'.route('notes.show', $source).'"', false);
    }

    #[DataProvider('targetTypes')]
    public function test_the_card_is_hidden_without_sources(LinkTargetType $type): void
    {
        $target = $this->makeFor($type);

        $this->actingAs($this->owner)
            ->get($this->showUrl($type, $target))
            ->assertOk()
            ->assertDontSee('Linked from')
            ->assertDontSee('data-linked-from', false);
    }

    public function test_the_card_names_each_source_kind_and_field(): void
    {
        $target = $this->makeFor(LinkTargetType::Codex);
        $chapter = Chapter::factory()->for(Act::factory()->for($this->project->books()->first()))->create();
        $scene = Scene::factory()->for($chapter)->create([
            'name' => 'The Saturday curse',
            'description' => $this->link(LinkTargetType::Codex, $target),
            'contents' => '[go](entity:codex/'.$target->getKey().')',
        ]);
        $event = Event::factory()->for($this->project)->create([
            'title' => 'Wedding feast',
            'description' => $this->link(LinkTargetType::Codex, $target),
        ]);
        $this->project->update(['description' => $this->link(LinkTargetType::Codex, $target)]);

        $html = $this->actingAs($this->owner)
            ->get(route('codex.show', $target))
            ->assertOk()
            ->assertSee('href="'.route('scenes.show', $scene).'"', false)
            ->assertSee('href="'.route('events.show', $event).'"', false)
            ->assertSee('href="'.route('projects.show', $this->project).'"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'The Saturday curse</a>'));
        $this->assertMatchesRegularExpression('/Scene\s*&middot;\s*Description, Prose/', $html);
        $this->assertMatchesRegularExpression('/Event\s*&middot;\s*Description/', $html);
        $this->assertMatchesRegularExpression('/Project\s*&middot;\s*Description/', $html);
    }

    public function test_the_card_lists_sources_by_title(): void
    {
        $target = $this->makeFor(LinkTargetType::Plotline);

        foreach (['Zebra', 'apple'] as $title) {
            Note::factory()->for($this->project)->create(['title' => $title, 'body' => $this->link(LinkTargetType::Plotline, $target)]);
        }

        $this->actingAs($this->owner)
            ->get(route('plotlines.show', $target))
            ->assertSeeInOrder(['apple', 'Zebra']);
    }

    public function test_the_card_skips_a_text_that_links_to_itself(): void
    {
        $note = Note::factory()->for($this->project)->create(['title' => 'Selfish note']);
        $note->update(['body' => $this->link(LinkTargetType::Note, $note)]);

        $this->actingAs($this->owner)
            ->get(route('notes.show', $note))
            ->assertOk()
            ->assertDontSee('data-linked-from', false);
    }

    public function test_the_card_skips_a_source_of_another_project(): void
    {
        $target = $this->makeFor(LinkTargetType::Codex);
        $otherOwner = User::factory()->create();
        [$other] = $this->projectWithBook($otherOwner);
        // The other project's text names an id that is a target here.
        Note::factory()->for($other)->create(['title' => 'Foreign note', 'body' => $this->link(LinkTargetType::Codex, $target)]);

        $this->actingAs($this->owner)
            ->get(route('codex.show', $target))
            ->assertOk()
            ->assertDontSee('Foreign note')
            ->assertDontSee('data-linked-from', false);
    }

    public function test_the_card_drops_a_source_after_its_link_is_removed_or_it_is_deleted(): void
    {
        $target = $this->makeFor(LinkTargetType::Event);
        $kept = Note::factory()->for($this->project)->create(['title' => 'Kept note', 'body' => $this->link(LinkTargetType::Event, $target)]);
        $edited = Note::factory()->for($this->project)->create(['title' => 'Edited note', 'body' => $this->link(LinkTargetType::Event, $target)]);
        $deleted = Note::factory()->for($this->project)->create(['title' => 'Deleted note', 'body' => $this->link(LinkTargetType::Event, $target)]);

        $edited->update(['body' => '<p>No link now.</p>']);
        $deleted->delete();

        $this->actingAs($this->owner)
            ->get(route('events.show', $target))
            ->assertSee('Kept note')
            ->assertDontSee('Edited note')
            ->assertDontSee('Deleted note');
    }

    public function test_a_page_runs_the_same_queries_for_one_source_or_many(): void
    {
        $target = $this->makeFor(LinkTargetType::Plotline);
        Note::factory()->for($this->project)->create(['body' => $this->link(LinkTargetType::Plotline, $target)]);

        $countQueries = function () use ($target): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('plotlines.show', $target))->assertOk();

            return count(DB::getQueryLog());
        };

        // The first request pays one-off queries (settings, session). Warm up first.
        $countQueries();
        $withOne = $countQueries();

        for ($i = 0; $i < 4; $i++) {
            Note::factory()->for($this->project)->create(['body' => $this->link(LinkTargetType::Plotline, $target)]);
        }

        $this->assertSame($withOne, $countQueries());
    }

    #[DataProvider('targetTypes')]
    public function test_a_non_owner_cannot_open_the_target_page(LinkTargetType $type): void
    {
        $target = $this->makeFor($type);
        Note::factory()->for($this->project)->create(['title' => 'Secret source', 'body' => $this->link($type, $target)]);

        $this->actingAs(User::factory()->create())
            ->get($this->showUrl($type, $target))
            ->assertForbidden();
    }
}
