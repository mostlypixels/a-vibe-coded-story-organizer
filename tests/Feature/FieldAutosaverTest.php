<?php

namespace Tests\Feature;

use App\Exceptions\RevisionConflictException;
use App\Models\Act;
use App\Models\Revision;
use App\Models\User;
use App\Services\FieldAutosaver;
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
}
