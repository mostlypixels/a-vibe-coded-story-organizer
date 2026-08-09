<?php

namespace Tests\Feature;

use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Support\WordCountHistoryGenerator;
use App\Support\WriterDay;
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
     * MelusineSeeder{En,Fr,It} write scenes through `$chapter->scenes()->create()`,
     * but `DatabaseSeeder` uses `WithoutModelEvents`, which wraps the whole seeded
     * run in `Model::withoutEvents()`. So `Scene::booted()`'s word_count hook never
     * fires here. Each Melusine seeder backfills the counts explicitly instead
     * (`Database\Seeders\Concerns\BackfillsSceneWordCounts`, the same shape as the
     * `scenes.word_count` migration's own backfill). This test pins that the seeded
     * data satisfies the invariant, not that the hook alone provides it.
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

    /**
     * Pins the seeded history against `WordCountHistoryGenerator::plan()` itself
     * rather than against the day count: rest days are absent by design, so a
     * plain "60 rows" assertion would be wrong on purpose.
     */
    public function test_a_seeded_project_has_the_generators_exact_history(): void
    {
        $this->seed();

        $project = Project::where('name', 'The Roman of Melusine')->firstOrFail();
        $total = (int) $project->sceneQuery()->sum('word_count');

        $days = 60;
        $plan = WordCountHistoryGenerator::plan($total, $days, crc32($project->name));
        $today = WriterDay::for($project->user);

        $expectedDates = collect($plan)
            ->map(fn (array $entry): string => $today->subDays($days - 1 - $entry['offset'])->toDateString())
            ->all();

        $actualDates = WordCountSnapshot::where('project_id', $project->id)
            ->orderBy('recorded_on')
            ->get()
            ->map(fn (WordCountSnapshot $snapshot): string => $snapshot->recorded_on->toDateString())
            ->all();

        $this->assertSame($expectedDates, $actualDates);
    }

    /**
     * The chart's last point must equal the header's word count, or the demo
     * discredits the whole feature (see demo-history.md).
     */
    public function test_a_seeded_projects_last_snapshot_equals_its_live_word_count(): void
    {
        $this->seed();

        foreach (['The Roman of Melusine', 'Le Roman de Mélusine', 'Il Romanzo di Melusina'] as $name) {
            $project = Project::where('name', $name)->firstOrFail();

            $lastSnapshot = WordCountSnapshot::where('project_id', $project->id)
                ->orderByDesc('recorded_on')
                ->firstOrFail();

            $this->assertSame(
                (int) $project->sceneQuery()->sum('word_count'),
                $lastSnapshot->word_count,
                "Last snapshot mismatch for {$name}.",
            );
        }
    }
}
