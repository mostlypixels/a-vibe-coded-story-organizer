<?php

namespace Tests\Feature;

use App\Models\CodexEntry;
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

    /**
     * Same gap as the word count above, on the other derived cache: nothing in a
     * seeded run goes through the controller paths that call
     * `SceneReferenceMatcher`, so `scene_codex_entry` stayed empty and every
     * Codex sheet in the demo showed no scenes. Each Melusine seeder now syncs
     * its own project (`Database\Seeders\Concerns\SyncsCodexReferences`).
     */
    public function test_seeded_scenes_are_linked_to_the_codex_entries_they_mention(): void
    {
        $this->seed();

        $castle = CodexEntry::query()->where('name', 'Castle of Lusignan')->firstOrFail();

        $this->assertGreaterThan(0, $castle->referencingScenes()->count());
    }

    /**
     * The sync is a full resync per project, so a second seeded run must leave
     * the pivot exactly as the first did — not a duplicated or widened set.
     */
    public function test_a_second_seeded_run_does_not_change_the_reference_set(): void
    {
        $this->seed();

        $castle = CodexEntry::query()->where('name', 'Castle of Lusignan')->firstOrFail();
        $referencesAfterFirstRun = $castle->referencingScenes()->pluck('scenes.id')->sort()->values()->all();

        $this->seed();

        $this->assertSame(
            $referencesAfterFirstRun,
            $castle->referencingScenes()->pluck('scenes.id')->sort()->values()->all(),
        );
    }
}
