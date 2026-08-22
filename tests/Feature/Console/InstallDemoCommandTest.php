<?php

namespace Tests\Feature\Console;

use App\Enums\ChallengeRecurrence;
use App\Enums\ChallengeState;
use App\Models\Challenge;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Services\ChallengeProgress;
use App\Support\WordCountHistoryGenerator;
use App\Support\WriterDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    private const MELUSINE_NAMES = [
        'The Roman of Melusine',
        'Le Roman de Mélusine',
        'Il Romanzo di Melusina',
    ];

    public function test_it_creates_the_three_melusine_projects_for_the_user(): void
    {
        $user = User::factory()->create(['email' => 'writer@example.com']);

        $this->artisan('app:install-demo', ['--user' => 'writer@example.com'])
            ->assertSuccessful();

        $names = [
            'The Roman of Melusine',
            'Le Roman de Mélusine',
            'Il Romanzo di Melusina',
        ];

        foreach ($names as $name) {
            $project = Project::where('user_id', $user->id)->where('name', $name)->first();

            $this->assertNotNull($project, "Expected project \"{$name}\" to exist.");
            $this->assertSame(1, $project->books()->count(), "Expected exactly one book for \"{$name}\".");
        }
    }

    public function test_a_second_run_is_a_no_op(): void
    {
        $user = User::factory()->create(['email' => 'writer@example.com']);

        $this->artisan('app:install-demo', ['--user' => 'writer@example.com'])->assertSuccessful();
        $this->artisan('app:install-demo', ['--user' => 'writer@example.com'])->assertSuccessful();

        $this->assertSame(3, Project::where('user_id', $user->id)->count());
    }

    public function test_it_populates_codex_references(): void
    {
        $user = User::factory()->create(['email' => 'writer@example.com']);

        $this->artisan('app:install-demo', ['--user' => 'writer@example.com'])->assertSuccessful();

        $project = Project::where('user_id', $user->id)->where('name', 'The Roman of Melusine')->firstOrFail();

        $this->assertTrue(CodexEntry::where('project_id', $project->id)->exists());

        $scene = $project->books()->first()->acts()->first()->chapters()->first()->scenes()->first();
        $this->assertGreaterThan(0, $scene->word_count);
    }

    public function test_it_resolves_the_user_by_id(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:install-demo', ['--user' => (string) $user->id])->assertSuccessful();

        $this->assertSame(3, Project::where('user_id', $user->id)->count());
    }

    public function test_it_fails_when_no_user_exists(): void
    {
        $this->artisan('app:install-demo')->assertFailed();
    }

    /**
     * `SceneReferenceMatcher` runs no controller path during a seed, so each
     * Melusine seeder syncs its own project. Without it every Codex sheet in the
     * demo shows no scenes.
     */
    public function test_seeded_scenes_are_linked_to_the_codex_entries_they_mention(): void
    {
        $this->installDemoFor(User::factory()->create());

        $castle = CodexEntry::query()->where('name', 'Castle of Lusignan')->firstOrFail();

        $this->assertGreaterThan(0, $castle->referencingScenes()->count());
    }

    /**
     * The sync is a full resync per project, so a second run must leave the pivot
     * exactly as the first did — not a duplicated or widened set.
     */
    public function test_a_second_run_does_not_change_the_reference_set(): void
    {
        $user = User::factory()->create();
        $this->installDemoFor($user);

        $castle = CodexEntry::query()->where('name', 'Castle of Lusignan')->firstOrFail();
        $referencesAfterFirstRun = $castle->referencingScenes()->pluck('scenes.id')->sort()->values()->all();

        $this->installDemoFor($user);

        $this->assertSame(
            $referencesAfterFirstRun,
            $castle->referencingScenes()->pluck('scenes.id')->sort()->values()->all(),
        );
    }

    /**
     * Pins the seeded history against `WordCountHistoryGenerator::plan()` itself
     * rather than a day count: rest days are absent by design, so a plain "60
     * rows" assertion would be wrong on purpose.
     */
    public function test_a_seeded_project_has_the_generators_exact_history(): void
    {
        $user = User::factory()->create();
        $this->installDemoFor($user);

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
        $this->installDemoFor(User::factory()->create());

        foreach (self::MELUSINE_NAMES as $name) {
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

    /**
     * Each Melusine project gets one finished challenge and one running monthly
     * challenge, both scored from the seeded history.
     */
    public function test_a_seeded_melusine_project_has_a_finished_and_a_running_challenge(): void
    {
        $this->installDemoFor(User::factory()->create());

        foreach (self::MELUSINE_NAMES as $name) {
            $project = Project::where('name', $name)->firstOrFail();
            $today = WriterDay::for($project->user);
            $progress = app(ChallengeProgress::class);

            $this->assertSame(2, Challenge::where('project_id', $project->id)->count(), "Challenge count for {$name}.");

            $finished = Challenge::where('project_id', $project->id)
                ->where('recurrence', ChallengeRecurrence::None)
                ->first();
            $running = Challenge::where('project_id', $project->id)
                ->where('recurrence', ChallengeRecurrence::Monthly)
                ->first();

            $this->assertNotNull($finished, "No finished challenge for {$name}.");
            $this->assertNotNull($running, "No running challenge for {$name}.");

            $finishedStanding = $progress->standing($finished, $today);
            $this->assertSame(ChallengeState::Finished, $finishedStanding->state, "Finished state for {$name}.");
            $this->assertTrue($finishedStanding->met, "Finished challenge should read met for {$name}.");

            $runningStanding = $progress->standing($running, $today);
            $this->assertSame(ChallengeState::Running, $runningStanding->state, "Running state for {$name}.");
        }
    }

    private function installDemoFor(User $user): void
    {
        $this->artisan('app:install-demo', ['--user' => (string) $user->id])->assertSuccessful();
    }
}
