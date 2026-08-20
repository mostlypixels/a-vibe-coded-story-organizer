<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Challenge;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Support\WordCountFormat;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressPageTest extends TestCase
{
    use RefreshDatabase;

    private function sceneWithWordsFor(Project $project, string $contents = 'one two three'): Scene
    {
        $book = $project->books()->first();
        $act = Act::factory()->for($book)->create();
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

    // --- Challenges ------------------------------------------------------

    public function test_running_upcoming_and_past_challenges_each_render_in_their_own_section(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-06-15');

        Challenge::factory()->for($project)->create([
            'name' => 'A Running Challenge',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'target_words' => 10000,
        ]);
        Challenge::factory()->for($project)->create([
            'name' => 'An Upcoming Challenge',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'target_words' => 10000,
        ]);
        Challenge::factory()->for($project)->create([
            'name' => 'A Past Challenge',
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-10',
            'target_words' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__('Upcoming'));
        $response->assertSee(__('Past'));
        $response->assertSee('A Running Challenge');
        $response->assertSee('An Upcoming Challenge');
        $response->assertSee('A Past Challenge');

        CarbonImmutable::setTestNow();
    }

    public function test_the_past_table_caps_at_twelve_rows_across_challenges(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-06-15');

        // Fifteen finished, non-overlapping windows, oldest first.
        for ($i = 0; $i < 15; $i++) {
            $start = CarbonImmutable::parse('2026-01-01')->addDays($i * 2);
            Challenge::factory()->for($project)->create([
                'name' => "Finished {$i}",
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->addDay()->toDateString(),
                'target_words' => 1000,
            ]);
        }

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        // Newest first, capped at twelve: the three oldest windows drop off.
        $response->assertSee('Finished 14');
        $response->assertSee('Finished 3');
        $response->assertDontSee('Finished 2');
        $response->assertDontSee('Finished 0');

        CarbonImmutable::setTestNow();
    }

    public function test_a_monthly_challenges_current_month_is_not_shown_as_past(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-06-15');

        Challenge::factory()->for($project)->monthly()->create([
            'name' => 'Every Month',
            'starts_on' => '2026-05-10',
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        // Once for the running card, once for May's finished row — never a
        // third time for June, which is still the running window.
        $this->assertSame(2, substr_count($response->getContent(), 'Every Month'));

        CarbonImmutable::setTestNow();
    }

    public function test_no_challenges_shows_the_empty_line_and_no_table(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__("You haven't started a challenge yet."));
        $response->assertDontSee(__('Past'));
        $response->assertDontSee(__('Upcoming'));
    }

    public function test_a_net_cut_challenge_renders_the_negative_figure_and_an_empty_bar(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-06-05');

        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => '2026-05-31',
            'word_count' => 5000,
        ]);
        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => '2026-06-04',
            'word_count' => 2000,
        ]);

        Challenge::factory()->for($project)->create([
            'name' => 'Net Cut Challenge',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'target_words' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        // written -3000, par round(10000 * 4/30) = 1333 (4 finished days of 30).
        $response->assertSee(__('Behind by'));
        $response->assertSee(WordCountFormat::text(4333));
        $response->assertSee('width: 0%', false);

        CarbonImmutable::setTestNow();
    }

    public function test_an_overshooting_challenge_shows_target_reached(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CarbonImmutable::setTestNow('2026-06-05');

        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => '2026-06-04',
            'word_count' => 1500,
        ]);

        Challenge::factory()->for($project)->create([
            'name' => 'Overshot Challenge',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'target_words' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('projects.progress', $project));

        $response->assertOk();
        $response->assertSee(__('Target reached'));
        $response->assertSee(WordCountFormat::text(1500), false);

        CarbonImmutable::setTestNow();
    }

    public function test_a_non_owner_gets_403_on_a_project_with_challenges(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        Challenge::factory()->for($project)->create();

        $this->actingAs($other)
            ->get(route('projects.progress', $project))
            ->assertForbidden();
    }
}
