<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Services\SceneResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneResolverTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $act = Act::factory()->for($this->project->books()->first())->create();
        $this->chapter = Chapter::factory()->for($act)->create();
    }

    private function scene(string $name, ?Chapter $chapter = null): Scene
    {
        return Scene::factory()->for($chapter ?? $this->chapter)->create(['name' => $name]);
    }

    private function resolve(string $reference, ?string $projectId = null): array
    {
        return app(SceneResolver::class)->resolve($reference, $projectId)->pluck('id')->all();
    }

    public function test_an_id_finds_one_scene(): void
    {
        $scene = $this->scene('Dawn');

        $this->assertSame([$scene->id], $this->resolve((string) $scene->id));
    }

    public function test_an_unknown_id_finds_nothing(): void
    {
        $this->assertSame([], $this->resolve('99999'));
    }

    public function test_an_exact_name_ignores_case(): void
    {
        $scene = $this->scene('Dawn');

        $this->assertSame([$scene->id], $this->resolve('dAWN'));
    }

    public function test_an_exact_name_wins_over_a_partial_match(): void
    {
        $exact = $this->scene('Dawn');
        $this->scene('Dawn at the Fountain');

        $this->assertSame([$exact->id], $this->resolve('Dawn'));
    }

    public function test_a_partial_name_finds_one_scene(): void
    {
        $scene = $this->scene('Dawn at the Fountain');
        $this->scene('Dusk');

        $this->assertSame([$scene->id], $this->resolve('Fountain'));
    }

    public function test_a_partial_name_can_find_several_scenes(): void
    {
        $first = $this->scene('Dawn at the Fountain');
        $second = $this->scene('Dusk at the Fountain');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->resolve('Fountain'));
    }

    public function test_an_unknown_name_finds_nothing(): void
    {
        $this->scene('Dawn');

        $this->assertSame([], $this->resolve('Moon'));
    }

    public function test_a_project_limits_the_search(): void
    {
        $mine = $this->scene('Dawn');
        $otherProject = Project::factory()->create();
        $otherAct = Act::factory()->for($otherProject->books()->first())->create();
        $this->scene('Dawn', Chapter::factory()->for($otherAct)->create());

        $this->assertSame([$mine->id], $this->resolve('Dawn', (string) $this->project->id));
    }

    public function test_an_unknown_project_throws(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->resolve('Dawn', '99999');
    }
}
