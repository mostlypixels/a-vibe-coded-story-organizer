<?php

namespace Tests\Unit\Services;

use App\Enums\FieldKind;
use App\Models\Book;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\RevisionComparison;
use App\Services\RevisionDiffer;
use App\Services\RevisionHistory;
use App\Support\RevisionDiffResult;
use App\Support\SavePoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/** Compare complete entity states and skip unchanged fields. */
class RevisionComparisonTest extends TestCase
{
    use RefreshDatabase;

    private RevisionComparison $comparison;

    private Book $book;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comparison = app(RevisionComparison::class);
        $this->user = User::factory()->create();
        [$this->project, $this->book] = $this->projectWithBook($this->user);
    }

    private function revision(array $attributes = []): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Book::class,
            'revisionable_id' => $this->book->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            ...$attributes,
        ]);
    }

    private function pointOf(Revision $revision): SavePoint
    {
        return collect(app(RevisionHistory::class)->savePoints($this->book))
            ->firstOrFail(fn (SavePoint $point): bool => $point->saveId === $revision->save_id);
    }

    public function test_a_changed_field_is_compared_over_the_two_values_it_held(): void
    {
        $from = $this->revision(['value' => '<p>The ferry left.</p>', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['value' => '<p>The ferry slipped away.</p>', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to));

        $this->assertCount(1, $comparisons);
        $this->assertSame('description', $comparisons[0]->field);
        $this->assertSame(FieldKind::Rich, $comparisons[0]->kind);
        $this->assertSame($from->id, $comparisons[0]->from->id);
        $this->assertSame($to->id, $comparisons[0]->to->id);
        $this->assertStringContainsString('slipped away', $comparisons[0]->result->html);
        $this->assertFalse($comparisons[0]->isNewField());
    }

    public function test_fields_that_did_not_change_are_absent(): void
    {
        // The rights notice was written before both points and never touched
        // again, so both sides resolve to the same revision.
        $this->revision(['field' => 'rights', 'value' => 'All rights reserved.', 'created_at' => now()->subDays(5)]);
        $from = $this->revision(['field' => 'description', 'value' => '<p>Old</p>', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['field' => 'description', 'value' => '<p>New</p>', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to));

        $this->assertSame(['description'], $comparisons->pluck('field')->all());
    }

    public function test_a_field_changed_by_an_unrelated_save_in_between_appears_as_changed(): void
    {
        // Neither of the two chosen save points touched the dedication. A save
        // between them did — and comparing two *states* has to report that.
        $from = $this->revision(['field' => 'description', 'value' => '<p>Old</p>', 'created_at' => now()->subDays(3)]);
        $this->revision(['field' => 'dedication', 'value' => 'For her', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['field' => 'description', 'value' => '<p>New</p>', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to));

        $this->assertEqualsCanonicalizing(['description', 'dedication'], $comparisons->pluck('field')->all());
    }

    public function test_a_field_that_did_not_exist_at_the_older_point_reads_as_wholly_new(): void
    {
        $from = $this->revision(['field' => 'description', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['field' => 'dedication', 'value' => 'For the ones who waited.', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to));

        $dedication = $comparisons->firstOrFail(fn ($comparison) => $comparison->field === 'dedication');

        $this->assertNull($dedication->from);
        $this->assertTrue($dedication->isNewField());
        $this->assertSame($to->id, $dedication->to->id);
        $this->assertStringContainsString('waited', $dedication->result->html);
    }

    public function test_comparisons_come_back_in_registry_field_order(): void
    {
        $from = $this->revision(['field' => 'rights', 'value' => 'Old rights', 'created_at' => now()->subDays(3)]);
        $this->revision(['field' => 'dedication', 'value' => 'For her', 'created_at' => now()->subDays(2)]);
        $this->revision(['field' => 'description', 'value' => '<p>New</p>', 'created_at' => now()->subDay()]);
        $to = $this->revision(['field' => 'rights', 'value' => 'New rights', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to));

        // description, dedication, rights — the order AutosavableFields declares,
        // not the order the saves happened in.
        $this->assertSame(['description', 'dedication', 'rights'], $comparisons->pluck('field')->all());
    }

    public function test_the_field_filter_returns_exactly_that_field(): void
    {
        $from = $this->revision(['field' => 'description', 'value' => '<p>Old</p>', 'created_at' => now()->subDays(3)]);
        $this->revision(['field' => 'dedication', 'value' => 'For her', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['field' => 'description', 'value' => '<p>New</p>', 'created_at' => now()]);

        $comparisons = $this->comparison->between($this->book, $this->pointOf($from), $this->pointOf($to), 'description');

        $this->assertCount(1, $comparisons);
        $this->assertSame('description', $comparisons[0]->field);
        // The filter narrows what is shown; it does not change which pair of
        // values the field is compared over.
        $this->assertSame($from->id, $comparisons[0]->from->id);
        $this->assertSame($to->id, $comparisons[0]->to->id);
    }

    public function test_two_points_with_nothing_between_them_compare_to_nothing(): void
    {
        $only = $this->revision(['field' => 'description', 'created_at' => now()]);
        $point = $this->pointOf($only);

        $this->assertCount(0, $this->comparison->between($this->book, $point, $point));
    }

    public function test_the_differ_runs_once_per_changed_field_and_never_for_an_unchanged_one(): void
    {
        // Two fields changed, three did not. Diffing is the expensive part of
        // this page, so it must happen exactly twice.
        $this->revision(['field' => 'rights', 'value' => 'All rights reserved.', 'created_at' => now()->subDays(5)]);
        $from = $this->revision(['field' => 'description', 'value' => '<p>Old</p>', 'created_at' => now()->subDays(3)]);
        $this->revision(['field' => 'dedication', 'value' => 'For her', 'created_at' => now()->subDays(2)]);
        $to = $this->revision(['field' => 'description', 'value' => '<p>New</p>', 'created_at' => now()]);

        $this->mock(RevisionDiffer::class, function (MockInterface $mock) {
            $mock->shouldReceive('diff')->twice()->andReturn(new RevisionDiffResult('<p>diffed</p>', 1));
        });

        $comparisons = app(RevisionComparison::class)->between($this->book, $this->pointOf($from), $this->pointOf($to));

        $this->assertCount(2, $comparisons);
    }
}
