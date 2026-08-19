<?php

namespace Tests\Unit\Services;

use App\Enums\RevisionOrigin;
use App\Models\Book;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\RevisionHistory;
use App\Support\SavePoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Group stored field revisions into save points. */
class RevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private RevisionHistory $history;

    private Book $book;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = app(RevisionHistory::class);
        $this->user = User::factory()->create();
        [$this->project, $this->book] = $this->projectWithBook($this->user);
    }

    /**
     * One revision row against $this->book, with everything a test does not
     * care about defaulted.
     */
    private function revision(array $attributes = []): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Book::class,
            'revisionable_id' => $this->book->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'field' => 'description',
            ...$attributes,
        ]);
    }

    private function saveId(): string
    {
        return (string) Str::ulid();
    }

    public function test_the_rows_of_one_save_fold_into_one_save_point_listing_both_fields(): void
    {
        $saveId = $this->saveId();

        $this->revision(['save_id' => $saveId, 'field' => 'description', 'summary_html' => '<ins>new</ins>', 'change_count' => 2]);
        $this->revision(['save_id' => $saveId, 'field' => 'dedication', 'summary_html' => '<ins>for her</ins>', 'change_count' => 1]);

        $page = $this->history->forEntity($this->book);

        $this->assertCount(1, $page->items());

        $point = $page->items()[0];
        $this->assertSame($saveId, $point->saveId);
        $this->assertSame($this->user->name, $point->authorName);
        // Registry field order, not write order: description is registered
        // before dedication, so it lists first however the rows were written.
        $this->assertSame(['description', 'dedication'], $point->entries->pluck('field')->all());
        $this->assertSame(2, $point->entries[0]->changeCount);
        $this->assertTrue($point->entries[0]->hasMoreChanges());
        $this->assertSame(1, $point->entries[0]->otherChangeCount());
        $this->assertFalse($point->entries[1]->hasMoreChanges());
    }

    public function test_save_points_are_newest_first_with_ties_broken_by_id(): void
    {
        $moment = now()->subHour();

        // Three save points stamped the same second — an autosave burst plus a
        // Save is exactly this. created_at alone cannot order them.
        $first = $this->revision(['save_id' => $this->saveId(), 'created_at' => $moment]);
        $second = $this->revision(['save_id' => $this->saveId(), 'created_at' => $moment]);
        $third = $this->revision(['save_id' => $this->saveId(), 'created_at' => $moment]);
        $older = $this->revision(['save_id' => $this->saveId(), 'created_at' => $moment->copy()->subDay()]);

        $saveIds = collect($this->history->forEntity($this->book)->items())->pluck('saveId')->all();

        $this->assertSame(
            [$third->save_id, $second->save_id, $first->save_id, $older->save_id],
            $saveIds,
        );
    }

    public function test_each_save_point_names_the_one_before_it(): void
    {
        $older = $this->revision(['save_id' => $this->saveId(), 'created_at' => now()->subDays(2)]);
        $newer = $this->revision(['save_id' => $this->saveId(), 'created_at' => now()->subDay()]);

        $points = collect($this->history->forEntity($this->book)->items());

        $this->assertSame($older->save_id, $points[0]->previousSaveId);
        $this->assertTrue($points[0]->hasPrevious());
        // The oldest save point has nothing before it.
        $this->assertNull($points[1]->previousSaveId);
        $this->assertFalse($points[1]->hasPrevious());
        $this->assertSame($newer->save_id, $points[0]->saveId);
    }

    public function test_the_field_filter_narrows_both_the_save_points_and_their_entries(): void
    {
        $mixed = $this->saveId();
        $this->revision(['save_id' => $mixed, 'field' => 'description']);
        $this->revision(['save_id' => $mixed, 'field' => 'dedication']);
        $this->revision(['save_id' => $this->saveId(), 'field' => 'dedication', 'created_at' => now()->subDay()]);

        $points = collect($this->history->forEntity($this->book, ['field' => 'description'])->items());

        // Only the save point that touched description, and only that entry of it.
        $this->assertCount(1, $points);
        $this->assertSame($mixed, $points[0]->saveId);
        $this->assertSame(['description'], $points[0]->entries->pluck('field')->all());
    }

    public function test_the_label_filter_matches_a_substring_and_keeps_the_whole_group(): void
    {
        $labelled = $this->saveId();
        $this->revision(['save_id' => $labelled, 'field' => 'description', 'label' => 'Saved 24 July 10:43']);
        $this->revision(['save_id' => $labelled, 'field' => 'dedication', 'label' => 'Saved 24 July 10:43']);
        $this->revision(['save_id' => $this->saveId(), 'label' => null, 'created_at' => now()->subDay()]);

        $points = collect($this->history->forEntity($this->book, ['label' => '24 July'])->items());

        $this->assertCount(1, $points);
        $this->assertSame('Saved 24 July 10:43', $points[0]->label);
        $this->assertCount(2, $points[0]->entries);
    }

    public function test_the_manual_only_filter_keeps_deliberate_checkpoints(): void
    {
        $manual = $this->saveId();
        $this->revision(['save_id' => $manual, 'origin' => RevisionOrigin::Manual, 'label' => 'Checkpoint']);
        $this->revision(['save_id' => $this->saveId(), 'origin' => RevisionOrigin::Automatic, 'created_at' => now()->subDay()]);

        $points = collect($this->history->forEntity($this->book, ['manualOnly' => true])->items());

        $this->assertCount(1, $points);
        $this->assertSame($manual, $points[0]->saveId);
    }

    public function test_a_manual_only_save_point_still_lists_the_fields_it_touched(): void
    {
        // A burst that was still open when the writer pressed Save leaves an
        // automatic row and a manual row in one group. The group qualifies as
        // manual — and must still describe everything that save touched.
        $mixed = $this->saveId();
        $this->revision(['save_id' => $mixed, 'field' => 'description', 'origin' => RevisionOrigin::Automatic]);
        $this->revision(['save_id' => $mixed, 'field' => 'dedication', 'origin' => RevisionOrigin::Manual]);

        $points = collect($this->history->forEntity($this->book, ['manualOnly' => true])->items());

        $this->assertCount(1, $points);
        $this->assertCount(2, $points[0]->entries);
    }

    public function test_filters_combine(): void
    {
        $wanted = $this->saveId();
        $this->revision(['save_id' => $wanted, 'field' => 'description', 'origin' => RevisionOrigin::Manual, 'label' => 'Before the rewrite']);
        // Right field, right label, wrong origin.
        $this->revision(['save_id' => $this->saveId(), 'field' => 'description', 'origin' => RevisionOrigin::Automatic, 'label' => 'Before the rewrite', 'created_at' => now()->subDay()]);
        // Right origin, right label, wrong field.
        $this->revision(['save_id' => $this->saveId(), 'field' => 'dedication', 'origin' => RevisionOrigin::Manual, 'label' => 'Before the rewrite', 'created_at' => now()->subDays(2)]);
        // Right field and origin, wrong label.
        $this->revision(['save_id' => $this->saveId(), 'field' => 'description', 'origin' => RevisionOrigin::Manual, 'label' => 'Something else', 'created_at' => now()->subDays(3)]);

        $points = collect($this->history->forEntity($this->book, [
            'field' => 'description',
            'label' => 'rewrite',
            'manualOnly' => true,
        ])->items());

        $this->assertCount(1, $points);
        $this->assertSame($wanted, $points[0]->saveId);
    }

    public function test_the_newest_save_point_is_marked_current(): void
    {
        $this->revision(['save_id' => $this->saveId(), 'created_at' => now()->subDay()]);
        $current = $this->revision(['save_id' => $this->saveId(), 'created_at' => now()]);

        $points = collect($this->history->forEntity($this->book)->items());

        $this->assertTrue($points[0]->isCurrent);
        $this->assertSame($current->save_id, $points[0]->saveId);
        $this->assertFalse($points[1]->isCurrent);
    }

    public function test_a_filter_never_promotes_an_older_save_point_to_current(): void
    {
        // The live state came from a save that only touched dedication. Filtering
        // to description must not tell the writer that an older save is her
        // current text.
        $older = $this->saveId();
        $this->revision(['save_id' => $older, 'field' => 'description', 'created_at' => now()->subDay()]);
        $this->revision(['save_id' => $this->saveId(), 'field' => 'dedication', 'created_at' => now()]);

        $points = collect($this->history->forEntity($this->book, ['field' => 'description'])->items());

        $this->assertCount(1, $points);
        $this->assertSame($older, $points[0]->saveId);
        $this->assertFalse($points[0]->isCurrent);
    }

    public function test_a_group_holding_a_manual_and_an_automatic_row_reads_as_manual(): void
    {
        $saveId = $this->saveId();
        $this->revision(['save_id' => $saveId, 'field' => 'description', 'origin' => RevisionOrigin::Automatic]);
        $this->revision(['save_id' => $saveId, 'field' => 'dedication', 'origin' => RevisionOrigin::Manual]);

        $points = collect($this->history->forEntity($this->book)->items());

        $this->assertSame(RevisionOrigin::Manual, $points[0]->origin);
        $this->assertFalse($points[0]->isBaseline());
    }

    public function test_the_origin_precedence_covers_every_origin_there_is(): void
    {
        // SavePoint::dominantOrigin() falls back when it meets an origin the
        // precedence list does not rank. Adding a case to RevisionOrigin without
        // ranking it here would silently mislabel save points, so this fails loudly.
        $this->assertEqualsCanonicalizing(
            RevisionOrigin::cases(),
            SavePoint::ORIGIN_PRECEDENCE,
        );

        foreach (RevisionOrigin::cases() as $origin) {
            $this->assertSame($origin, SavePoint::dominantOrigin([$origin]));
        }
    }

    public function test_a_baseline_save_point_says_so(): void
    {
        $this->revision(['save_id' => $this->saveId(), 'origin' => RevisionOrigin::Baseline, 'summary_html' => null, 'change_count' => 0]);

        $point = collect($this->history->forEntity($this->book)->items())[0];

        $this->assertTrue($point->isBaseline());
        $this->assertNull($point->entries[0]->summaryHtml);
        $this->assertSame(0, $point->entries[0]->changeCount);
    }

    public function test_pagination_pages_by_save_point_and_never_renders_the_boundary_group(): void
    {
        config(['revisions.history.per_page' => 3]);

        // Five save points, newest first when listed.
        $saveIds = [];

        for ($index = 0; $index < 5; $index++) {
            $revision = $this->revision([
                'save_id' => $this->saveId(),
                'created_at' => now()->subDays(5 - $index),
            ]);
            $saveIds[] = $revision->save_id;
        }

        $newestFirst = array_reverse($saveIds);

        $first = $this->history->forEntity($this->book, page: 1);
        $second = $this->history->forEntity($this->book, page: 2);

        // Three rendered, not four: the fourth group was fetched only so the
        // third row could name the save point before it.
        $this->assertCount(3, $first->items());
        $this->assertSame(array_slice($newestFirst, 0, 3), collect($first->items())->pluck('saveId')->all());
        $this->assertSame($newestFirst[3], collect($first->items())[2]->previousSaveId);

        $this->assertSame(5, $first->total());
        $this->assertSame(2, $first->lastPage());

        $this->assertCount(2, $second->items());
        $this->assertSame(array_slice($newestFirst, 3, 2), collect($second->items())->pluck('saveId')->all());
        // Nothing before the oldest save point in existence.
        $this->assertNull(collect($second->items())[1]->previousSaveId);
    }

    public function test_save_points_returns_the_whole_unpaginated_list_for_the_pickers(): void
    {
        config(['revisions.history.per_page' => 2]);

        for ($index = 0; $index < 5; $index++) {
            $this->revision(['save_id' => $this->saveId(), 'created_at' => now()->subDays(5 - $index)]);
        }

        $points = $this->history->savePoints($this->book);

        $this->assertCount(5, $points);
        $this->assertTrue($points[0]->isCurrent);
        $this->assertNull($points[4]->previousSaveId);
    }

    public function test_history_never_leaks_another_entitys_save_points(): void
    {
        // The queries descend from the relation to its underlying builder to be
        // able to group and select; if that ever dropped the morph constraints,
        // every entity would show every other one's history.
        $other = Book::factory()->for($this->project)->create();
        $mine = $this->revision(['save_id' => $this->saveId()]);

        Revision::factory()->create([
            'revisionable_type' => Book::class,
            'revisionable_id' => $other->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'save_id' => $this->saveId(),
        ]);

        $points = collect($this->history->forEntity($this->book)->items());

        $this->assertCount(1, $points);
        $this->assertSame($mine->save_id, $points[0]->saveId);
        $this->assertSame(1, $this->history->forEntity($this->book)->total());
    }

    public function test_an_entity_with_no_history_pages_cleanly(): void
    {
        $page = $this->history->forEntity($this->book);

        $this->assertSame(0, $page->total());
        $this->assertCount(0, $page->items());
        $this->assertCount(0, $this->history->savePoints($this->book));
    }

    public function test_no_query_this_service_runs_ever_selects_a_revisions_value(): void
    {
        $saveId = $this->saveId();
        $this->revision(['save_id' => $saveId, 'field' => 'description']);
        $this->revision(['save_id' => $saveId, 'field' => 'dedication']);
        $this->revision(['save_id' => $this->saveId(), 'created_at' => now()->subDay()]);

        $selects = [];
        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'from "revisions"')) {
                $selects[] = $query->sql;
            }
        });

        $this->history->forEntity($this->book, ['field' => 'description']);
        $this->history->savePoints($this->book);

        $this->assertNotEmpty($selects, 'the listener caught nothing — the assertion below would pass vacuously');

        foreach ($selects as $sql) {
            $this->assertStringNotContainsString(
                '"value"',
                $sql,
                "A history query hydrated `value`. That column can be megabytes of scene contents:\n{$sql}",
            );
        }
    }
}
