<?php

namespace Tests\Feature\Console;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\WordCountSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WordCountDemoHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sets `word_count` with a raw `DB::table()` update, bypassing
     * `Scene::booted()`'s save hook (which derives it from `contents`) — the
     * same pattern `BackfillsSceneWordCounts` uses to give a scene an exact,
     * arbitrary total for the test.
     */
    private function projectWithWords(int $wordCount): Project
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        DB::table('scenes')->where('id', $scene->id)->update(['word_count' => $wordCount]);

        return $project;
    }

    public function test_it_generates_history_ending_on_the_projects_real_total(): void
    {
        $project = $this->projectWithWords(12000);

        $this->artisan('word-count:demo-history', ['project' => $project->id])->assertSuccessful();

        $this->assertGreaterThan(0, WordCountSnapshot::where('project_id', $project->id)->count());

        $lastSnapshot = WordCountSnapshot::where('project_id', $project->id)
            ->orderByDesc('recorded_on')
            ->firstOrFail();

        $this->assertSame(12000, $lastSnapshot->word_count);
    }

    public function test_it_is_idempotent_on_a_second_run(): void
    {
        $project = $this->projectWithWords(12000);

        $this->artisan('word-count:demo-history', ['project' => $project->id])->assertSuccessful();
        $firstRunCount = WordCountSnapshot::where('project_id', $project->id)->count();
        $firstRunRows = WordCountSnapshot::where('project_id', $project->id)
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'word_count'])
            ->all();

        $this->artisan('word-count:demo-history', ['project' => $project->id])->assertSuccessful();

        $this->assertSame($firstRunCount, WordCountSnapshot::where('project_id', $project->id)->count());
        $this->assertEquals(
            $firstRunRows,
            WordCountSnapshot::where('project_id', $project->id)->orderBy('recorded_on')->get(['recorded_on', 'word_count'])->all(),
        );
    }

    public function test_it_accepts_a_days_option(): void
    {
        $project = $this->projectWithWords(5000);

        $this->artisan('word-count:demo-history', ['project' => $project->id, '--days' => 10])->assertSuccessful();

        $dates = WordCountSnapshot::where('project_id', $project->id)->pluck('recorded_on');

        $this->assertLessThanOrEqual(10, $dates->count());
        $this->assertGreaterThan(0, $dates->count());
    }

    public function test_it_fails_for_an_unknown_project_id(): void
    {
        $this->artisan('word-count:demo-history', ['project' => 999999])->assertFailed();
    }
}
