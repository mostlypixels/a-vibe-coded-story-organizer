<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the read-only Story overview: it authorizes via the project
 * and renders the nested act -> chapter -> scene tree ordered by `position`.
 */
class StoryTest extends TestCase
{
    use RefreshDatabase;

    /** A scene with exactly $wordCount words, built from a repeated token. */
    private function sceneWithWordCount(Chapter $chapter, int $wordCount): Scene
    {
        return Scene::factory()->for($chapter)->create([
            'contents' => trim(str_repeat('word ', $wordCount)),
        ]);
    }

    public function test_the_story_overview_renders_the_full_act_chapter_scene_tree(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['name' => 'The First Act']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'The First Chapter']);
        Scene::factory()->for($chapter)->create(['name' => 'The First Scene']);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSee('The First Act')
            ->assertSee('The First Chapter')
            ->assertSee('The First Scene');
    }

    public function test_a_user_cannot_view_another_users_story_overview(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('projects.story.index', $project))
            ->assertForbidden();
    }

    public function test_the_story_overview_orders_acts_by_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        // Create out of position order to prove the view sorts, not insertion order.
        Act::factory()->for($project)->create(['name' => 'Later Act', 'position' => 2]);
        Act::factory()->for($project)->create(['name' => 'Earlier Act', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSeeInOrder(['Earlier Act', 'Later Act']);
    }

    public function test_the_story_overview_orders_scenes_within_a_chapter_by_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        Scene::factory()->for($chapter)->create(['name' => 'Second Scene', 'position' => 2]);
        Scene::factory()->for($chapter)->create(['name' => 'First Scene', 'position' => 1]);

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSeeInOrder(['First Scene', 'Second Scene']);
    }

    // ---------------------------------------------------------------------
    // Task 8 (word-count spec) — chapter/act/project totals, at zero extra
    // queries. Scenes are already eager-loaded by StoryController::index(),
    // so every total below is a PHP sum() over that loaded data.
    // ---------------------------------------------------------------------

    /**
     * Every total below is distinct *and* none is a tail of another, which is
     * the stronger property this needs: `assertSee` is a substring match, so
     * with round numbers a chapter's "50 words" is satisfied by its act's
     * "1,050 words" and the assertion passes with that chapter's total never
     * rendered at all. The deliberately unround values below (1,343 / 1,062 /
     * 1,015 / 47 / 281 / 213 / 68) leave no such overlap, so each assertion
     * can only be met by the element it names.
     */
    public function test_chapter_act_and_project_totals_are_the_sum_of_their_scenes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $actOne = Act::factory()->for($project)->create();
        $chapterA = Chapter::factory()->for($actOne)->create();
        $this->sceneWithWordCount($chapterA, 613);
        $this->sceneWithWordCount($chapterA, 402); // chapter A total: 1,015
        $chapterB = Chapter::factory()->for($actOne)->create();
        $this->sceneWithWordCount($chapterB, 47); // chapter B total: 47
        // Act One total: 1,062

        $actTwo = Act::factory()->for($project)->create();
        $chapterC = Chapter::factory()->for($actTwo)->create();
        $this->sceneWithWordCount($chapterC, 213); // chapter C total: 213
        $chapterD = Chapter::factory()->for($actTwo)->create();
        $this->sceneWithWordCount($chapterD, 68); // chapter D total: 68
        // Act Two total: 281

        // Project total: 1,343

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSee('1,343 words') // project
            ->assertSee('1,062 words') // Act One
            ->assertSee('1,015 words') // Chapter A
            ->assertSee('47 words') // Chapter B
            ->assertSee('281 words') // Act Two
            ->assertSee('213 words') // Chapter C
            ->assertSee('68 words'); // Chapter D
    }

    public function test_an_act_with_no_chapters_and_a_chapter_with_no_scenes_render_zero_words(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Act with no chapters at all: its total, and the empty-chapters
        // message, must both render — not an error, not a blank total.
        Act::factory()->for($project)->create();

        // Act with one chapter that has no scenes.
        $actTwo = Act::factory()->for($project)->create();
        Chapter::factory()->for($actTwo)->create();

        $response = $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk();

        // Four zero totals render: the empty act, the empty chapter, that
        // chapter's act (only chapter it has is empty), and the project as a
        // whole. Counting occurrences (not just assertSee) proves every level
        // actually reached "0 words" rather than one lucky match covering
        // for a level that rendered blank.
        $this->assertSame(4, substr_count($response->getContent(), '0 words'));
    }

    /**
     * A heading's own text is its accessible name. With the total nested inside
     * the `<h2>`/`<h3>`, screen-reader heading navigation announced "Act 1 —
     * Melusine's Youth 490 words", and the act was silently renamed every time
     * the writer added a sentence — so the count sits *beside* the heading, in
     * the same flex row.
     *
     * Asserted against each heading's own inner HTML rather than the page as a
     * whole: on the whole page "613 words" is present either way, which is the
     * shape of assertion that would pass with the count moved back inside.
     */
    public function test_totals_render_beside_the_act_and_chapter_headings_not_inside_them(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $this->sceneWithWordCount($chapter, 613);

        $content = $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->assertSee('613 words') // It does render — just not in the headings.
            ->getContent();

        foreach ([['h2', 'act', $act->id], ['h3', 'chapter', $chapter->id]] as [$tag, $prefix, $id]) {
            $this->assertSame(
                1,
                preg_match('/<'.$tag.'[^>]*id="'.$prefix.'-'.$id.'"[^>]*>(.*?)<\/'.$tag.'>/s', $content, $matches),
                "Expected one <$tag id=\"$prefix-$id\"> on the story overview."
            );

            $this->assertStringNotContainsString(
                'words',
                $matches[1],
                "The $prefix total is inside its <$tag>, which makes the count part of the "
                .'heading\'s accessible name. Render it as a sibling in the same flex row.'
            );
        }
    }

    /**
     * The whole point of task 8: the story overview already eager-loads
     * every scene (`StoryController::index()`'s `with('chapters.scenes.event')`),
     * so summing chapter/act/project totals must not add a single query no
     * matter how many acts, chapters, or scenes exist. Counting queries
     * against the "scenes" table specifically (rather than the whole
     * request's query count, which also includes session/auth queries and
     * would be a flaky number to pin) isolates exactly the query this task
     * must not add.
     */
    public function test_totals_add_no_queries_against_the_scenes_table(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        foreach (range(1, 3) as $actNumber) {
            $act = Act::factory()->for($project)->create();

            foreach (range(1, 3) as $chapterNumber) {
                $chapter = Chapter::factory()->for($act)->create();

                foreach (range(1, 3) as $sceneNumber) {
                    $this->sceneWithWordCount($chapter, $sceneNumber * 10);
                }
            }
        }

        $sceneQueries = [];
        DB::listen(function ($query) use (&$sceneQueries) {
            if (str_contains($query->sql, '"scenes"')) {
                $sceneQueries[] = $query->sql;
            }
        });

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk();

        // One query loads every scene up front (the eager load); summing 3
        // acts x 3 chapters x 3 scenes into totals must not add any more.
        $this->assertCount(1, $sceneQueries);
    }

    // ---------------------------------------------------------------------
    // Continuous numbering (continuous-numbering, task 4) — the story
    // overview's TOC, headings and per-scene labels take their numbers from
    // StoryNumbering::fromActs() instead of the raw, per-parent `position`.
    // ---------------------------------------------------------------------

    public function test_toc_and_headings_show_continuous_chapter_numbers_across_the_act_boundary(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $actOne = Act::factory()->for($project)->create();
        Chapter::factory()->for($actOne)->create();
        Chapter::factory()->for($actOne)->create();

        // Act two's first chapter is chapter 3 project-wide, even though its
        // own `position` within act two is 1.
        $actTwo = Act::factory()->for($project)->create();
        Chapter::factory()->for($actTwo)->create();

        $content = $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->getContent();

        // Both the TOC and the body heading render "Chapter 3" for the
        // chapter that leads act two — twice, once in each location.
        $this->assertSame(2, substr_count($content, 'Chapter 3'));
        $this->assertStringNotContainsString('Chapter 4', $content);
    }

    public function test_act_numbers_close_the_gap_left_by_a_deleted_act(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $actOne = Act::factory()->for($project)->create(['name' => 'Act One']);
        $actTwo = Act::factory()->for($project)->create(['name' => 'Act Two']);
        $actThree = Act::factory()->for($project)->create(['name' => 'Act Three']);

        $actTwo->delete();

        // `position` is untouched by the deletion — act three keeps its
        // original, now-gappy position — but the rendered number compacts.
        $this->assertSame(3, $actThree->fresh()->position);

        $content = $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk()
            ->getContent();

        // Both the TOC and the body heading render "Act 2" for the act that
        // is now second, even though its own `position` is still 3.
        $this->assertSame(2, substr_count($content, 'Act 2'));
        $this->assertStringContainsString('Act Three', $content);
        $this->assertStringNotContainsString('Act 3 ', $content);
    }

    public function test_scene_numbers_render_and_run_unbroken_across_chapters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $chapterOne = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapterOne)->create(['name' => 'Scene A']);
        Scene::factory()->for($chapterOne)->create(['name' => 'Scene B']);

        $chapterTwo = Chapter::factory()->for($act)->create();
        Scene::factory()->for($chapterTwo)->create(['name' => 'Scene C']);

        $response = $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk();

        $response->assertSeeInOrder([
            'data-scene-number', '1.', 'Scene A',
            'data-scene-number', '2.', 'Scene B',
            'data-scene-number', '3.', 'Scene C',
        ], escape: false);
    }

    /**
     * `StoryNumbering::fromActs()` derives from the tree StoryController::index()
     * already eager-loaded — it must fire zero further queries against the
     * scenes table, on top of the one guarded by
     * test_totals_add_no_queries_against_the_scenes_table() above.
     */
    public function test_deriving_scene_numbers_adds_no_extra_queries_against_the_scenes_table(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        Scene::factory()->count(3)->for($chapter)->create();

        $sceneQueries = [];
        DB::listen(function ($query) use (&$sceneQueries) {
            if (str_contains($query->sql, '"scenes"')) {
                $sceneQueries[] = $query->sql;
            }
        });

        $this->actingAs($user)
            ->get(route('projects.story.index', $project))
            ->assertOk();

        $this->assertCount(1, $sceneQueries);
    }
}
