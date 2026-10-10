<?php

namespace Tests\Unit;

use App\Enums\NoteLinkType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoteLinkTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{NoteLinkType}> */
    public static function linkTypes(): array
    {
        return array_combine(
            array_map(fn (NoteLinkType $case) => $case->value, NoteLinkType::cases()),
            array_map(fn (NoteLinkType $case) => [$case], NoteLinkType::cases()),
        );
    }

    /** One row of the type in the given project. */
    private function makeFor(NoteLinkType $type, Project $project): Model
    {
        $book = $project->books()->first();

        return match ($type) {
            NoteLinkType::Book => $book,
            NoteLinkType::Act => Act::factory()->for($book)->create(),
            NoteLinkType::Chapter => Chapter::factory()->for(Act::factory()->for($book)->create())->create(),
            NoteLinkType::Scene => Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($book)->create())->create())->create(),
            NoteLinkType::Event => Event::factory()->for($project)->create(),
            NoteLinkType::Plotline => Plotline::factory()->for($project)->create(),
            NoteLinkType::Codex => CodexEntry::factory()->for($project)->create(),
        };
    }

    #[DataProvider('linkTypes')]
    public function test_query_for_returns_only_the_given_projects_rows(NoteLinkType $type): void
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
    public function test_from_model_returns_the_matching_case(NoteLinkType $type): void
    {
        $model = $this->makeFor($type, Project::factory()->create());

        $this->assertSame($type, NoteLinkType::fromModel($model));
        $this->assertInstanceOf($type->modelClass(), $model);
    }

    #[DataProvider('linkTypes')]
    public function test_the_show_route_exists(NoteLinkType $type): void
    {
        $this->assertTrue(Route::has($type->showRoute()));
        $this->assertNotSame('', $type->label());
    }

    public function test_events_include_the_start_and_end_bookends(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(2, NoteLinkType::Event->queryFor($project)->where('is_fixed', true)->count());
    }

    public function test_from_model_rejects_a_model_that_is_not_linkable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NoteLinkType::fromModel(Project::factory()->create());
    }
}
