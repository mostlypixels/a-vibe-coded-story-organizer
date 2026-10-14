<?php

namespace Tests\Unit;

use App\Enums\LinkTargetType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkTargetTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{LinkTargetType}> */
    public static function linkTypes(): array
    {
        return array_combine(
            array_map(fn (LinkTargetType $case) => $case->value, LinkTargetType::cases()),
            array_map(fn (LinkTargetType $case) => [$case], LinkTargetType::cases()),
        );
    }

    /** One row of the type in the given project. */
    private function makeFor(LinkTargetType $type, Project $project): Model
    {
        $book = $project->books()->first();

        return match ($type) {
            LinkTargetType::Book => $book,
            LinkTargetType::Act => Act::factory()->for($book)->create(),
            LinkTargetType::Chapter => Chapter::factory()->for(Act::factory()->for($book)->create())->create(),
            LinkTargetType::Scene => Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($book)->create())->create())->create(),
            LinkTargetType::Event => Event::factory()->for($project)->create(),
            LinkTargetType::Plotline => Plotline::factory()->for($project)->create(),
            LinkTargetType::Codex => CodexEntry::factory()->for($project)->create(),
            LinkTargetType::Note => Note::factory()->for($project)->create(),
        };
    }

    #[DataProvider('linkTypes')]
    public function test_query_for_returns_only_the_given_projects_rows(LinkTargetType $type): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $mine = $this->makeFor($type, $project);
        $foreign = $this->makeFor($type, $other);

        $ids = collect($type->queryFor($project)->get()->modelKeys());

        $this->assertTrue($ids->contains($mine->getKey()));
        $this->assertFalse($ids->contains($foreign->getKey()));
    }

    #[DataProvider('linkTypes')]
    public function test_from_model_returns_the_matching_case(LinkTargetType $type): void
    {
        $model = $this->makeFor($type, Project::factory()->create());

        $this->assertSame($type, LinkTargetType::fromModel($model));
        $this->assertInstanceOf($type->modelClass(), $model);
    }

    #[DataProvider('linkTypes')]
    public function test_the_show_route_exists(LinkTargetType $type): void
    {
        $this->assertTrue(Route::has($type->showRoute()));
        $this->assertNotSame('', $type->label());
    }

    public function test_the_stored_values_never_change(): void
    {
        $this->assertSame(
            ['book', 'act', 'chapter', 'scene', 'event', 'plotline', 'codex', 'note'],
            array_column(LinkTargetType::cases(), 'value'),
        );
    }

    public function test_only_a_note_is_not_note_linkable(): void
    {
        $this->assertFalse(LinkTargetType::Note->noteLinkable());
        $this->assertNotContains(LinkTargetType::Note, LinkTargetType::noteLinkableCases());
        $this->assertCount(count(LinkTargetType::cases()) - 1, LinkTargetType::noteLinkableCases());
    }

    public function test_a_note_label_is_its_title(): void
    {
        $note = Note::factory()->create(['title' => 'Castle research']);

        $this->assertSame('Castle research', LinkTargetType::Note->labelFor($note));
    }

    public function test_events_include_the_start_and_end_bookends(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(2, LinkTargetType::Event->queryFor($project)->where('is_fixed', true)->count());
    }

    public function test_from_model_rejects_a_model_that_is_not_linkable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LinkTargetType::fromModel(Project::factory()->create());
    }
}
