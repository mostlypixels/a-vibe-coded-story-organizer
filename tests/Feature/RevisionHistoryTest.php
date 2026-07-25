<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Task 13 — the entity history page: one row per *save point*, which is the
 * event the writer remembers making, rather than one row per field revision.
 *
 * `?field=`, `?label=`, `?manual=` and `?page=` are the whole state, so the
 * page is a pure GET — these tests drive it entirely through URLs.
 */
class RevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user): Act
    {
        return Act::factory()->for(Project::factory()->for($user)->create())->create();
    }

    private function sceneFor(User $user): Scene
    {
        return Scene::factory()->for(Chapter::factory()->for($this->actFor($user))->create())->create();
    }

    private function revisionFor(Act $act, array $overrides = []): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->project->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            'user_id' => $act->project->user_id,
            ...$overrides,
        ]);
    }

    private function sceneRevision(Scene $scene, array $overrides = []): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Scene::class,
            'revisionable_id' => $scene->id,
            'project_id' => $scene->chapter->act->project->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            'user_id' => $scene->chapter->act->project->user_id,
            ...$overrides,
        ]);
    }

    private function historyUrl(Act $act, array $query = []): string
    {
        return route('revisions.index', ['entity' => 'act', 'id' => $act->id, ...$query]);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_the_owner_sees_the_history(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['label' => 'A checkpoint']);

        $this->actingAs($user)->get($this->historyUrl($act))
            ->assertOk()
            ->assertSee('A checkpoint');
    }

    public function test_a_non_owner_gets_403(): void
    {
        $act = $this->actFor(User::factory()->create());
        $this->revisionFor($act);

        $this->actingAs(User::factory()->create())
            ->get($this->historyUrl($act))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $act = $this->actFor(User::factory()->create());

        $this->get($this->historyUrl($act))->assertRedirect(route('login'));
    }

    public function test_reading_history_needs_only_view_while_revert_needs_update(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $revision = $this->revisionFor($act, ['value' => 'An older description']);

        // ProjectPolicy::view and ::update are identical today, so the altitude
        // is only observable by forcing them apart: grant `view`, deny `update`.
        $this->partialMock(ProjectPolicy::class, function (MockInterface $mock) {
            $mock->shouldReceive('view')->andReturnTrue();
            $mock->shouldReceive('update')->andReturnFalse();
        });

        $this->actingAs($user)->get($this->historyUrl($act))->assertOk();
        $this->actingAs($user)->get(route('revisions.compare', ['entity' => 'act', 'id' => $act->id]))->assertOk();
        $this->actingAs($user)->get(route('projects.revisions.index', $act->project))->assertOk();

        $this->actingAs($user)
            ->post(route('revisions.revert', $revision), ['base_hash' => hash('sha256', (string) $act->description)])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Save points
    // ---------------------------------------------------------------------

    public function test_one_row_per_save_point_naming_every_field_it_touched(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $saveId = (string) Str::ulid();

        $this->sceneRevision($scene, ['save_id' => $saveId, 'field' => 'description', 'summary_html' => '<ins>a new description</ins>', 'change_count' => 1]);
        $this->sceneRevision($scene, ['save_id' => $saveId, 'field' => 'notes', 'summary_html' => '<ins>a new note</ins>', 'change_count' => 1]);

        $response = $this->actingAs($user)->get(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id]));

        $response->assertOk();
        // One save, two fields — not two rows that happen to share a timestamp.
        $this->assertSame(1, substr_count($response->getContent(), 'aria-labelledby="save-'));
        $response->assertSeeInOrder(['Description', 'a new description', 'Notes', 'a new note']);
    }

    public function test_save_points_are_listed_newest_first(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['label' => 'Older entry', 'created_at' => now()->subMinutes(10)]);
        $this->revisionFor($act, ['label' => 'Newer entry', 'created_at' => now()]);

        $this->actingAs($user)->get($this->historyUrl($act))
            ->assertOk()
            ->assertSeeInOrder(['Newer entry', 'Older entry']);
    }

    public function test_the_newest_save_point_carries_the_current_badge(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['label' => 'Older entry', 'created_at' => now()->subDay()]);
        $this->revisionFor($act, ['label' => 'Newest entry', 'created_at' => now()]);

        $response = $this->actingAs($user)->get($this->historyUrl($act));

        // The badge sits in the newest row and nowhere below it.
        $response->assertOk();
        $response->assertSeeInOrder(['Newest entry', 'Current', 'Older entry']);
    }

    public function test_a_baseline_save_point_renders_as_the_initial_value(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['origin' => RevisionOrigin::Baseline, 'label' => null, 'summary_html' => null, 'change_count' => 0]);

        $this->actingAs($user)->get($this->historyUrl($act))
            ->assertOk()
            ->assertSee('Initial value — before revision history');
    }

    public function test_a_summary_renders_without_becoming_markup_it_should_not_be(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        // summary_html is rendered with {!! !!} because its producer escapes.
        // A value that reached the column some other way must still not become
        // live markup here.
        $this->revisionFor($act, [
            'summary_html' => '<ins class="diff-ins">Salt &amp; pepper</ins> &lt;script&gt;alert(1)&lt;/script&gt;',
            'change_count' => 1,
        ]);

        $response = $this->actingAs($user)->get($this->historyUrl($act));

        $response->assertOk();
        $response->assertSee('Salt &amp; pepper', escape: false);
        $response->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_a_multi_change_entry_links_to_the_compare_page_with_the_pair_prefilled(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $older = $this->revisionFor($act, ['created_at' => now()->subDay()]);
        $newer = $this->revisionFor($act, ['created_at' => now(), 'summary_html' => '<ins>first</ins>', 'change_count' => 4]);

        $response = $this->actingAs($user)->get($this->historyUrl($act));

        $response->assertOk();
        $response->assertSee('and 3 more changes');
        $response->assertSee(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'from' => $older->save_id, 'to' => $newer->save_id,
        ]));
    }

    // ---------------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------------

    public function test_the_field_filter_narrows_the_list(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->sceneRevision($scene, ['field' => 'description', 'summary_html' => '<ins>desc change</ins>', 'change_count' => 1]);
        $this->sceneRevision($scene, ['field' => 'notes', 'summary_html' => '<ins>note change</ins>', 'change_count' => 1, 'created_at' => now()->subDay()]);

        $response = $this->actingAs($user)->get(route('revisions.index', [
            'entity' => 'scene', 'id' => $scene->id, 'field' => 'description',
        ]));

        $response->assertOk();
        $response->assertSee('desc change');
        $response->assertDontSee('note change');
    }

    public function test_an_unregistered_field_filter_404s(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act);

        $this->actingAs($user)
            ->get($this->historyUrl($act, ['field' => 'not_a_field']))
            ->assertNotFound();
    }

    public function test_the_label_search_narrows_the_list(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['label' => 'Alpha checkpoint']);
        $this->revisionFor($act, ['label' => 'Beta checkpoint', 'created_at' => now()->subDay()]);

        $this->actingAs($user)->get($this->historyUrl($act, ['label' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha checkpoint')
            ->assertDontSee('Beta checkpoint');
    }

    public function test_the_manual_only_filter_hides_autosaves(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->revisionFor($act, ['origin' => RevisionOrigin::Manual, 'label' => 'Deliberate save']);
        $this->revisionFor($act, ['origin' => RevisionOrigin::Automatic, 'label' => 'Autosaved', 'created_at' => now()->subDay()]);

        $this->actingAs($user)->get($this->historyUrl($act, ['manual' => 1]))
            ->assertOk()
            ->assertSee('Deliberate save')
            ->assertDontSee('Autosaved');
    }

    public function test_the_three_filters_combine(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->sceneRevision($scene, ['field' => 'description', 'origin' => RevisionOrigin::Manual, 'label' => 'Wanted rewrite']);
        $this->sceneRevision($scene, ['field' => 'notes', 'origin' => RevisionOrigin::Manual, 'label' => 'Wanted rewrite', 'created_at' => now()->subDay()]);
        $this->sceneRevision($scene, ['field' => 'description', 'origin' => RevisionOrigin::Automatic, 'label' => 'Wanted rewrite', 'created_at' => now()->subDays(2)]);
        $this->sceneRevision($scene, ['field' => 'description', 'origin' => RevisionOrigin::Manual, 'label' => 'Some other note', 'created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->get(route('revisions.index', [
            'entity' => 'scene', 'id' => $scene->id, 'field' => 'description', 'label' => 'Wanted', 'manual' => 1,
        ]));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'aria-labelledby="save-'));
    }

    public function test_filters_that_match_nothing_say_so(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act, ['label' => 'Something']);

        $this->actingAs($user)->get($this->historyUrl($act, ['label' => 'nothing matches this']))
            ->assertOk()
            ->assertSee('No saves match these filters.');
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    public function test_pagination_pages_by_save_point_and_keeps_every_row_comparable(): void
    {
        config(['revisions.history.per_page' => 3]);

        $user = User::factory()->create();
        $act = $this->actFor($user);

        $saveIds = [];

        for ($index = 0; $index < 5; $index++) {
            $saveIds[] = $this->revisionFor($act, ['created_at' => now()->subDays(5 - $index)])->save_id;
        }

        $newestFirst = array_reverse($saveIds);

        $first = $this->actingAs($user)->get($this->historyUrl($act));
        $first->assertOk();
        $this->assertSame(3, substr_count($first->getContent(), 'aria-labelledby="save-'));

        // The last row of page 1 still names the save point before it — that is
        // what the boundary group RevisionHistory fetches beyond the page is for.
        $first->assertSee(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'from' => $newestFirst[3], 'to' => $newestFirst[2],
        ]));

        $second = $this->actingAs($user)->get($this->historyUrl($act, ['page' => 2]));
        $second->assertOk();
        $this->assertSame(2, substr_count($second->getContent(), 'aria-labelledby="save-'));
        $second->assertSee(route('revisions.compare', [
            'entity' => 'act', 'id' => $act->id, 'from' => $newestFirst[4], 'to' => $newestFirst[3],
        ]));
    }

    public function test_the_paginator_carries_the_filters_across_pages(): void
    {
        config(['revisions.history.per_page' => 2]);

        $user = User::factory()->create();
        $act = $this->actFor($user);

        for ($index = 0; $index < 4; $index++) {
            $this->revisionFor($act, ['origin' => RevisionOrigin::Manual, 'label' => 'Kept', 'created_at' => now()->subDays(4 - $index)]);
        }

        $response = $this->actingAs($user)->get($this->historyUrl($act, ['manual' => 1, 'label' => 'Kept']));

        // Paging must not silently widen what the reader is looking at.
        $response->assertOk();
        $response->assertSee('manual=1', escape: false);
        $response->assertSee('label=Kept', escape: false);
    }

    // ---------------------------------------------------------------------
    // The legacy field-scoped URL
    // ---------------------------------------------------------------------

    public function test_the_legacy_field_scoped_url_redirects_to_the_filtered_page(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $this->revisionFor($act);

        // One concept, one page: the field-scoped history is the same page with
        // ?field= set.
        $this->actingAs($user)
            ->get(route('revisions.field', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']))
            ->assertRedirect($this->historyUrl($act, ['field' => 'description']));
    }

    public function test_the_legacy_url_404s_for_a_field_that_is_not_registered(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $this->actingAs($user)
            ->get(route('revisions.field', ['entity' => 'act', 'id' => $act->id, 'field' => 'not_a_field']))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // The invariant the whole read model rests on
    // ---------------------------------------------------------------------

    public function test_the_history_page_never_reads_a_stored_value(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $marker = 'SECRET-REVISION-VALUE-MARKER-'.str_repeat('x', 200);
        $this->revisionFor($act, ['value' => "<p>{$marker}</p>"]);

        $selects = [];
        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'from "revisions"')) {
                $selects[] = $query->sql;
            }
        });

        $response = $this->actingAs($user)->get($this->historyUrl($act));

        $response->assertOk();
        $response->assertDontSee($marker);

        $this->assertNotEmpty($selects);

        foreach ($selects as $sql) {
            $this->assertStringNotContainsString(
                '"value"',
                $sql,
                "The history page hydrated `value`. It can be megabytes of scene contents:\n{$sql}",
            );
        }
    }

    public function test_the_sidebar_still_links_only_to_fields_that_have_history(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        foreach (['description', 'notes'] as $field) {
            $this->sceneRevision($scene, ['field' => $field]);
        }

        $response = $this->actingAs($user)->get(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id]));

        $response->assertOk();
        $response->assertSee(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'notes']), escape: false);
        $response->assertDontSee(route('revisions.index', ['entity' => 'scene', 'id' => $scene->id, 'field' => 'contents']), escape: false);
    }
}
