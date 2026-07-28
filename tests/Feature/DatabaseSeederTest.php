<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_can_run_twice_without_failing(): void
    {
        // A second `db:seed` against a populated database used to abort on the
        // users.email UNIQUE constraint before MelusineSeeder was ever reached.
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_the_seeder_does_not_duplicate_the_demo_projects_on_a_second_run(): void
    {
        // Each MelusineSeeder{En,Fr,It} used to `Project::create()` unconditionally,
        // so a second `db:seed` (or `make seed` run twice) silently doubled every
        // demo project instead of no-op'ing.
        $this->seed();
        $this->seed();

        $this->assertSame(3, Project::count());
        $this->assertSame(1, Project::where('name', 'The Roman of Melusine')->count());
        $this->assertSame(1, Project::where('name', 'Le Roman de Mélusine')->count());
        $this->assertSame(1, Project::where('name', 'Il Romanzo di Melusina')->count());
    }

    /**
     * word-count spec, data-model.md "Seeding": MelusineSeeder{En,Fr,It} write
     * scenes through `$chapter->scenes()->create()`, but `DatabaseSeeder` uses
     * `WithoutModelEvents`, which wraps the whole seeded run in
     * `Model::withoutEvents()` — so `Scene::booted()`'s word_count hook (task
     * 4) never actually fires here. Each Melusine seeder backfills it
     * explicitly instead (`Database\Seeders\Concerns\BackfillsSceneWordCounts`,
     * same shape as the `scenes.word_count` migration's own backfill). This
     * pins that the seeded data still satisfies the invariant, not that the
     * hook alone provides it.
     */
    public function test_a_seeded_scene_has_a_non_zero_word_count(): void
    {
        $this->seed();

        $scene = Scene::query()->whereNotNull('contents')->where('contents', '!=', '')->firstOrFail();

        $this->assertGreaterThan(0, $scene->word_count);
    }
}
