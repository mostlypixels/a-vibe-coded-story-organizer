<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Act;
use App\Models\Revision;
use App\Models\User;
use App\Services\FieldAutosaver;
use App\Services\RevisionRecorder;
use App\Support\FieldHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The autosave service without the HTTP layer. The endpoint tests cover the rest. */
class FieldAutosaverTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user): Act
    {
        [, $book] = $this->projectWithBook($user);

        return Act::factory()->for($book)->create(['description' => '<p>Old</p>']);
    }

    public function test_a_save_returns_the_stored_value_and_records_an_automatic_revision(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        $result = app(FieldAutosaver::class)->save($act, 'description', '<p>New text</p>', FieldHash::of('<p>Old</p>'), $user);

        $this->assertSame('<p>New text</p>', $act->fresh()->description);
        $this->assertSame(FieldHash::of('<p>New text</p>'), $result->hash());
        $this->assertSame(2, $result->wordCount);
        $this->assertSame(Revision::query()->latest('id')->value('id'), $result->revisionId);
    }

    public function test_a_stale_base_hash_throws_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);

        try {
            app(FieldAutosaver::class)->save($act, 'description', '<p>New</p>', FieldHash::of('<p>Stale</p>'), $user);
            $this->fail('A stale base hash must throw.');
        } catch (RevisionConflictException) {
            // Expected.
        }

        $this->assertSame('<p>Old</p>', $act->fresh()->description);
        $this->assertSame(0, Revision::query()->count());
    }

    /** The blur flush and "Save and stay" can race. Both write the same text. */
    public function test_an_autosave_that_loses_a_race_to_a_manual_save_records_no_duplicate(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user);
        $staleAct = Act::findOrFail($act->id);

        // The manual save lands after the autosave read the row.
        $before = ['description' => '<p>Old</p>'];
        $act->update(['description' => '<p>New</p>']);
        app(RevisionRecorder::class)->recordManualChanges($act, $before, $user);

        app(FieldAutosaver::class)->save($staleAct, 'description', '<p>New</p>', FieldHash::of('<p>Old</p>'), $user);

        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Automatic)->count());
    }
}
