<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tools ▸ Progress (ProgressController): the status strip that always shows
 * *now* — today's words and the running total against the project's two
 * goals. The chart and its range picker join this page in a later task.
 */
class ProgressPageTest extends TestCase
{
    use RefreshDatabase;

    private function sceneWithWordsFor(Project $project, string $contents = 'one two three'): Scene
    {
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create(['contents' => $contents]);
    }

    public function test_owner_sees_both_figures(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => 500,
            'total_word_goal' => 10000,
        ]);
        $this->sceneWithWordsFor($project);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__('Today'));
        $response->assertSee(__('Total'));
    }

    public function test_a_project_with_no_goals_renders_the_strip_without_goal_rows(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => null,
            'total_word_goal' => null,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertDontSee(__('Today'));
        $response->assertDontSee(__('Total'));
    }

    public function test_a_project_with_only_a_daily_goal_renders_only_that_row(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => 500,
            'total_word_goal' => null,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__('Today'));
        $response->assertDontSee(__('Total'));
    }

    public function test_a_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.progress', $project))
            ->assertForbidden();
    }

    public function test_the_tools_progress_link_appears_on_a_project_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(route('projects.progress', $project), false);
    }

    public function test_the_progress_page_marks_the_progress_link_active(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('projects.progress', $project), '/').'"[^>]*aria-current="page"/',
            $response->getContent(),
        );
    }

    public function test_owner_sees_the_chart_when_snapshots_exist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => CarbonImmutable::now()->toDateString(),
            'word_count' => 500,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee('data-variant="full"', false);
        $response->assertDontSee(__('No writing recorded yet.'));
    }

    public function test_a_project_with_no_snapshots_shows_the_empty_state(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__('No writing recorded yet.'));
    }

    public function test_the_default_range_is_the_current_month_in_the_owners_timezone(): void
    {
        // Tokyo is ahead of UTC: at 2026-03-01 23:00 UTC it is already
        // 2026-03-02 in Tokyo. A server-zone default would put "today" in
        // February and materialise the wrong month's series.
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-03-01 23:00:00');

        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => WriterDay::dateFor($user),
            'word_count' => 250,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertViewHas('from', fn (CarbonImmutable $from) => $from->toDateString() === '2026-03-01');
        $response->assertViewHas('to', fn (CarbonImmutable $to) => $to->toDateString() === '2026-03-31');

        CarbonImmutable::setTestNow();
    }

    public function test_from_after_to_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', [
            'project' => $project,
            'from' => '2026-03-10',
            'to' => '2026-03-01',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_a_span_over_366_days_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', [
            'project' => $project,
            'from' => '2024-01-01',
            'to' => '2026-01-05',
        ]));

        $response->assertSessionHasErrors('to');
    }

    public function test_a_valid_explicit_range_is_used(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', [
            'project' => $project,
            'from' => '2026-03-01',
            'to' => '2026-03-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('from', fn (CarbonImmutable $from) => $from->toDateString() === '2026-03-01');
        $response->assertViewHas('to', fn (CarbonImmutable $to) => $to->toDateString() === '2026-03-31');
    }
}
