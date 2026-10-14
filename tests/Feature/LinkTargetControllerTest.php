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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkTargetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->project = Project::factory()->for($this->owner)->create();
    }

    /** @return array<string, array{LinkTargetType}> */
    public static function linkTypes(): array
    {
        return array_combine(
            array_map(fn (LinkTargetType $case) => $case->value, LinkTargetType::cases()),
            array_map(fn (LinkTargetType $case) => [$case], LinkTargetType::cases()),
        );
    }

    /** One row of the type in the given project, named `$name`. */
    private function makeFor(LinkTargetType $type, Project $project, string $name): Model
    {
        $book = $project->books()->first();
        $chapter = fn (array $attributes = []) => Chapter::factory()->for(Act::factory()->for($book)->create())->create($attributes);

        return match ($type) {
            LinkTargetType::Book => $project->books()->create(['name' => $name]),
            LinkTargetType::Act => Act::factory()->for($book)->create(['name' => $name]),
            LinkTargetType::Chapter => $chapter(['name' => $name]),
            LinkTargetType::Scene => Scene::factory()->for($chapter())->create(['name' => $name]),
            LinkTargetType::Event => Event::factory()->for($project)->create(['title' => $name]),
            LinkTargetType::Plotline => Plotline::factory()->for($project)->create(['name' => $name]),
            LinkTargetType::Codex => CodexEntry::factory()->for($project)->create(['name' => $name]),
            LinkTargetType::Note => Note::factory()->for($project)->create(['title' => $name]),
        };
    }

    #[DataProvider('linkTypes')]
    public function test_owner_gets_candidates_of_this_project_only(LinkTargetType $type): void
    {
        $mine = $this->makeFor($type, $this->project, 'Melusine');
        $this->makeFor($type, Project::factory()->for($this->other)->create(), 'Melusine abroad');

        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => $type->value, 'q' => 'Melu']))
            ->assertOk()
            ->assertExactJson([['type' => $type->value, 'id' => $mine->getKey(), 'label' => 'Melusine']]);
    }

    #[DataProvider('linkTypes')]
    public function test_the_search_filters_the_candidates(LinkTargetType $type): void
    {
        $mine = $this->makeFor($type, $this->project, 'Melusine');
        $this->makeFor($type, $this->project, 'Raymondin');

        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => $type->value, 'q' => 'Melu']))
            ->assertOk()
            ->assertExactJson([['type' => $type->value, 'id' => $mine->getKey(), 'label' => 'Melusine']]);

        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => $type->value]))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Raymondin']);
    }

    public function test_the_list_is_limited(): void
    {
        Note::factory()->for($this->project)->count(LinkTargetType::CANDIDATE_LIMIT + 3)->create();

        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => 'note']))
            ->assertOk()
            ->assertJsonCount(LinkTargetType::CANDIDATE_LIMIT);
    }

    public function test_the_type_must_be_valid(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => 'tag']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->actingAs($this->owner)
            ->getJson(route('projects.link-targets', $this->project))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_a_non_owner_cannot_read_candidates(): void
    {
        $this->actingAs($this->other)
            ->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => 'scene']))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->getJson(route('projects.link-targets', ['project' => $this->project, 'type' => 'scene']))
            ->assertUnauthorized();
    }
}
