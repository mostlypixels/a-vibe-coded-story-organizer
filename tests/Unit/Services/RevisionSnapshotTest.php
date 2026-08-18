<?php

namespace Tests\Unit\Services;

use App\Models\Book;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use App\Services\RevisionHistory;
use App\Services\RevisionSnapshot;
use App\Support\SavePoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Resolve a complete entity state at one save point. */
class RevisionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private RevisionSnapshot $snapshots;

    private Book $book;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshots = app(RevisionSnapshot::class);
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

    /**
     * The save point a given revision belongs to, resolved the way the real
     * caller (the compare page) will: through RevisionHistory.
     */
    private function pointOf(Revision $revision): SavePoint
    {
        return collect(app(RevisionHistory::class)->savePoints($this->book))
            ->firstOrFail(fn (SavePoint $point): bool => $point->saveId === $revision->save_id);
    }

    public function test_a_snapshot_resolves_fields_the_save_never_touched(): void
    {
        // The description was written first and never again; a later save
        // touched only the dedication. As of that later save the description
        // still held its original value, and the snapshot has to say so.
        $description = $this->revision(['field' => 'description', 'value' => 'Early description', 'created_at' => now()->subDays(3)]);
        $dedication = $this->revision(['field' => 'dedication', 'value' => 'For her', 'created_at' => now()->subDay()]);

        $snapshot = $this->snapshots->asOf($this->book, $this->pointOf($dedication));

        $this->assertSame($description->id, $snapshot->revisionIdFor('description'));
        $this->assertSame($dedication->id, $snapshot->revisionIdFor('dedication'));
    }

    public function test_a_snapshot_covers_every_registered_field_of_the_entity(): void
    {
        $only = $this->revision(['field' => 'description']);

        $snapshot = $this->snapshots->asOf($this->book, $this->pointOf($only));

        // Project registers six fields; all six are present, five of them null.
        $this->assertSame(
            ['description', 'dedication', 'acknowledgements', 'preface', 'postface', 'rights'],
            array_keys($snapshot->fields),
        );
        $this->assertNull($snapshot->revisionFor('preface'));
    }

    public function test_a_field_written_after_the_moment_is_not_in_the_snapshot(): void
    {
        $moment = $this->revision(['field' => 'description', 'created_at' => now()->subDays(2)]);
        $this->revision(['field' => 'dedication', 'created_at' => now()]);

        $snapshot = $this->snapshots->asOf($this->book, $this->pointOf($moment));

        $this->assertSame($moment->id, $snapshot->revisionIdFor('description'));
        // Written later — as of this moment the dedication did not exist yet.
        $this->assertNull($snapshot->revisionFor('dedication'));
    }

    public function test_ties_inside_one_second_resolve_by_id(): void
    {
        // An autosave burst and the Save that closed it land in the same second.
        // A timestamp-only bound would pick between them at the database's whim.
        $moment = now()->subHour();

        $earlier = $this->revision(['field' => 'description', 'value' => 'Draft', 'created_at' => $moment]);
        $later = $this->revision(['field' => 'description', 'value' => 'Saved', 'created_at' => $moment]);

        $this->assertSame($earlier->id, $this->snapshots->asOf($this->book, $this->pointOf($earlier))->revisionIdFor('description'));
        $this->assertSame($later->id, $this->snapshots->asOf($this->book, $this->pointOf($later))->revisionIdFor('description'));
    }

    public function test_current_resolves_the_newest_revision_of_every_field(): void
    {
        $this->revision(['field' => 'description', 'value' => 'Old', 'created_at' => now()->subDays(2)]);
        $newest = $this->revision(['field' => 'description', 'value' => 'New', 'created_at' => now()]);
        $rights = $this->revision(['field' => 'rights', 'value' => 'All rights reserved.', 'created_at' => now()->subDay()]);

        $snapshot = $this->snapshots->current($this->book);

        $this->assertNull($snapshot->point);
        $this->assertSame($newest->id, $snapshot->revisionIdFor('description'));
        $this->assertSame($rights->id, $snapshot->revisionIdFor('rights'));
        $this->assertNull($snapshot->revisionFor('preface'));
    }

    public function test_resolving_a_moment_never_loads_a_stored_value(): void
    {
        $revision = $this->revision(['field' => 'description', 'value' => str_repeat('a', 5000)]);
        $point = $this->pointOf($revision);

        $selects = [];
        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'from "revisions"')) {
                $selects[] = $query->sql;
            }
        });

        $this->snapshots->asOf($this->book, $point);
        $this->snapshots->current($this->book);

        $this->assertNotEmpty($selects);

        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('"value"', $sql, "A snapshot query hydrated `value`:\n{$sql}");
        }
    }

    public function test_a_snapshot_never_reaches_into_another_entitys_history(): void
    {
        $other = Book::factory()->for($this->project)->create();
        $mine = $this->revision(['field' => 'description', 'created_at' => now()->subDay()]);

        Revision::factory()->create([
            'revisionable_type' => Book::class,
            'revisionable_id' => $other->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'field' => 'description',
            'save_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        $snapshot = $this->snapshots->current($this->book);

        $this->assertSame($mine->id, $snapshot->revisionIdFor('description'));
    }
}
