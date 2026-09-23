<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Non-owner checks for routes that the per-resource test files do not cover.
 * Each route needs only its model, so one data provider replaces a copy of the same test per route.
 */
class NonOwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function ownedRoutes(): array
    {
        return [
            'project edit' => ['get', 'projects.edit', 'project'],
            'project destroy' => ['delete', 'projects.destroy', 'project'],
            'book create' => ['get', 'projects.books.create', 'project'],
            'plotline create' => ['get', 'projects.plotlines.create', 'project'],
            'plotline edit' => ['get', 'plotlines.edit', 'plotline'],
            'book edit' => ['get', 'books.edit', 'book'],
            'act create' => ['get', 'books.acts.create', 'book'],
            'chapter create' => ['get', 'books.chapters.create', 'book'],
            'scene create' => ['get', 'books.scenes.create', 'book'],
            'act move up' => ['patch', 'acts.move-up', 'act'],
            'chapter edit' => ['get', 'chapters.edit', 'chapter'],
            'chapter move up' => ['patch', 'chapters.move-up', 'chapter'],
            'scene move up' => ['patch', 'scenes.move-up', 'scene'],
        ];
    }

    #[DataProvider('ownedRoutes')]
    public function test_a_non_owner_gets_403(string $method, string $route, string $model): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $book = Book::factory()->for($project)->create();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $models = [
            'project' => $project,
            'plotline' => Plotline::factory()->for($project)->create(),
            'book' => $book,
            'act' => $act,
            'chapter' => $chapter,
            'scene' => Scene::factory()->for($chapter)->create(),
        ];

        $this->actingAs(User::factory()->create())
            ->{$method}(route($route, $models[$model]))
            ->assertForbidden();

        $this->assertModelExists($project);
    }
}
