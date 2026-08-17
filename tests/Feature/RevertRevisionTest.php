<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Act;
use App\Models\Revision;
use App\Models\User;
use App\Services\RevisionRecorder;
use App\Services\RevisionReverter;
use App\View\Components\RevisionsLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * RevisionController::revert() and the App\Services\RevisionReverter behind it
 * copy an older revision's value back onto the live column, additively
 * (expanded/architecture.md "Revert"). Revert is never destructive: the
 * reverted-away-from state and every other row stay as they were, and revert
 * always creates a new `origin: revert` row.
 *
 * These tests drive the service through the HTTP endpoint, not in isolation.
 * The base-hash check, the re-validation and the recorded row are all things a
 * request must end up with. The conflict *response* belongs here too, beside
 * the revert behaviour it guards.
 */
class RevertRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user, array $overrides = []): Act
    {
        [$project, $book] = $this->projectWithBook($user);

        return Act::factory()->for($book)->create($overrides);
    }

    private function revisionFor(Act $act, array $overrides = []): Revision
    {
        return Revision::factory()->create(array_merge([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->book->project->id,
            'field' => 'description',
        ], $overrides));
    }

    private function hashOf(?string $value): string
    {
        return hash('sha256', $value ?? '');
    }

    public function test_reverting_updates_the_live_column_and_creates_exactly_one_new_revision(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        $countBefore = Revision::count();

        $response = $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf($act->description),
        ]);

        $response->assertRedirect();

        $act->refresh();
        $this->assertSame('<p>Older text</p>', $act->description);
        $this->assertSame($countBefore + 1, Revision::count());

        // The original row this reverted to is untouched.
        $this->assertSame('<p>Older text</p>', $old->fresh()->value);
        $this->assertSame($old->origin, $old->fresh()->origin);
    }

    public function test_the_new_revision_is_an_origin_revert_row_with_an_auto_generated_label(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay()->setTime(9, 12),
        ]);

        $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf($act->description),
        ]);

        $revert = Revision::query()->where('origin', RevisionOrigin::Revert)->firstOrFail();

        $this->assertSame('<p>Older text</p>', $revert->value);
        $this->assertSame(
            'Reverted to '.$old->created_at->format('d F H:i'),
            $revert->label,
        );
    }

    public function test_reverting_a_rich_field_re_runs_sanitization(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        // A <script> tag would never survive a normal save (SanitizesRichHtml
        // strips it on assignment) — seeding a revision that still contains one
        // proves revert isn't just a raw column overwrite that skips mutators.
        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p><script>alert(1)</script>',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf($act->description),
        ]);

        $act->refresh();
        $this->assertStringNotContainsString('<script>', $act->description);
        $this->assertStringContainsString('Older text', $act->description);
    }

    /**
     * A conflict redirects back with an error alert the writer can act on. It
     * must never abort(409) into a bare error page: the writer did nothing
     * wrong.
     */
    public function test_a_stale_base_hash_redirects_back_with_an_error_and_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        $countBefore = Revision::count();

        $response = $this->actingAs($user)->post(route('revisions.revert', $old), [
            'base_hash' => 'not-the-real-hash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas(RevisionsLayout::ERROR_KEY);
        $response->assertSessionMissing('status');

        $act->refresh();
        $this->assertSame('<p>Current text</p>', $act->description);
        $this->assertSame($countBefore, Revision::count());
    }

    /**
     * The regression guard for the other half of that decision: the two
     * conflict surfaces were split on purpose. A browser POST gets a page; the
     * JSON autosave endpoint keeps the 409 *status*, because a client reads it.
     */
    public function test_the_autosave_endpoint_still_answers_the_same_conflict_with_409_json(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']),
            ['value' => '<p>Fresh text</p>', 'base_hash' => 'not-the-real-hash'],
        )->assertStatus(409);

        $act->refresh();
        $this->assertSame('<p>Current text</p>', $act->description);
    }

    public function test_a_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $act = $this->actFor($owner, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $owner->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($other)->post(route('revisions.revert', $old), [
            'base_hash' => $this->hashOf($act->description),
        ])->assertForbidden();

        $act->refresh();
        $this->assertSame('<p>Current text</p>', $act->description);
    }

    /**
     * Every conflict test above asserts the app *decided* to flash a message.
     * None asserts that a page renders it. Without this test the alert could sit
     * in the wrong shell, or carry the wrong props, and the whole suite would
     * still pass with the alert never reaching the writer.
     */
    public function test_the_conflict_alert_is_actually_rendered_on_the_page_it_returns_to(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        $historyUrl = route('revisions.index', ['entity' => 'act', 'id' => $act->id]);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->from($historyUrl)
            ->post(route('revisions.revert', $old), ['base_hash' => 'not-the-real-hash']);

        $response->assertOk();
        // The message names the field that moved — a
        // compare page shows several at once, each with its own revert button —
        // and no longer tells the writer to reload, which is a step the redirect
        // has already taken for them.
        $response->assertSee('"Description" changed somewhere else while this page was open');
        $response->assertDontSee('reload and try again');
    }

    /**
     * The base hash must not be checked against a model already hydrated in
     * memory, with the value written afterwards — two steps, and nothing holds
     * the row still in between. Two reverts that arrive together both pass that
     * check, and the second silently overwrites the first: exactly the outcome
     * the base hash exists to prevent.
     *
     * A real race cannot be scheduled in a test, so this reproduces its *shape*.
     * The row moves after the reverter has been handed an entity whose in-memory
     * copy still hashes to what the page showed, so the cheap pre-flight check
     * passes and only the locked re-read inside the write transaction can catch
     * it. The service is driven directly here rather than through the endpoint,
     * because the window being closed opens after the controller has resolved
     * the model.
     */
    public function test_a_value_that_moves_after_the_preflight_check_is_still_refused(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        $baseHash = $this->hashOf($act->description);

        // Somebody else writes the column. Straight to the database, so the
        // model the reverter is about to be handed still holds — and still
        // hashes to — what the page was showing.
        DB::table('acts')->where('id', $act->id)->update([
            'description' => '<p>Someone else was here</p>',
        ]);

        $countBefore = Revision::count();

        try {
            app(RevisionReverter::class)->revertField($act, $old, $baseHash, $user);
            $this->fail('The revert should have been refused: the row moved after the pre-flight check.');
        } catch (RevisionConflictException $exception) {
            $this->assertStringContainsString('Description', $exception->getMessage());
        }

        // The other writer's text survives, and no revert row was recorded.
        $this->assertSame('<p>Someone else was here</p>', $act->fresh()->description);
        $this->assertSame($countBefore, Revision::count());
    }

    /**
     * A revert re-validates the old value against *today's* rules. Rules can
     * tighten after a value is recorded, and an old value must not reach the
     * column through a door a normal save closes.
     *
     * The failure message must reach the writer. In `$errors` alone it is
     * invisible: no revisions view renders that bag, so the page comes back
     * identical — no message, no change, no explanation.
     */
    public function test_a_revert_that_fails_todays_validation_explains_itself_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>A value that was perfectly legal when it was saved</p>',
            'created_at' => now()->subDay(),
        ]);

        // The rules tighten *after* that value was recorded — the exact situation
        // the re-validation exists for.
        config(['revisions.caps' => ['default' => 10]]);

        $countBefore = Revision::count();
        $historyUrl = route('revisions.index', ['entity' => 'act', 'id' => $act->id]);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->from($historyUrl)
            ->post(route('revisions.revert', $old), [
                'base_hash' => $this->hashOf($act->description),
            ]);

        $response->assertOk();
        // The writer is told what happened, and which rule refused it — naming
        // the field, not the internal "value" key.
        $response->assertSee('That value cannot be restored as it stands.', false);
        $response->assertSee('Description', false);

        // And nothing was written: not the column, not a revision row.
        $act->refresh();
        $this->assertSame('<p>Current text</p>', $act->description);
        $this->assertSame($countBefore, Revision::count());
    }

    /**
     * `restore()` must change the column and record the change in one
     * transaction. As two separate statements, a failure in the second moves
     * the value with nothing in the history to say so — the one outcome this
     * whole feature exists to prevent.
     */
    public function test_a_failure_recording_the_revert_leaves_the_column_untouched(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Current text</p>']);

        $old = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Older text</p>',
            'created_at' => now()->subDay(),
        ]);

        // The second write blows up after the first has already succeeded.
        $this->mock(RevisionRecorder::class, function (MockInterface $recorder) {
            $recorder->shouldReceive('record')->andThrow(new RuntimeException('history write failed'));
        });

        try {
            $this->actingAs($user)->post(route('revisions.revert', $old), [
                'base_hash' => $this->hashOf($act->description),
            ]);
        } catch (RuntimeException) {
            // Whether the exception surfaces here or is rendered as a 500 by the
            // handler is beside the point — the database state is.
        }

        $act->refresh();
        $this->assertSame('<p>Current text</p>', $act->description);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Revert)->count());
    }

    public function test_reverting_twice_undoes_the_revert_and_both_are_visible_in_history(): void
    {
        $user = User::factory()->create();
        $act = $this->actFor($user, ['description' => '<p>Version A</p>']);

        $versionB = $this->revisionFor($act, [
            'user_id' => $user->id,
            'value' => '<p>Version B</p>',
            'created_at' => now()->subDay(),
        ]);

        // Revert to B.
        $this->actingAs($user)->post(route('revisions.revert', $versionB), [
            'base_hash' => $this->hashOf($act->description),
        ])->assertRedirect();

        $act->refresh();
        $this->assertSame('<p>Version B</p>', $act->description);

        $revisionOfA = Revision::factory()->create([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->book->project->id,
            'field' => 'description',
            'user_id' => $user->id,
            'value' => '<p>Version A</p>',
            'created_at' => now()->subDays(2),
        ]);

        // Revert again, back to A — undoing the first revert.
        $this->actingAs($user)->post(route('revisions.revert', $revisionOfA), [
            'base_hash' => $this->hashOf($act->description),
        ])->assertRedirect();

        $act->refresh();
        $this->assertSame('<p>Version A</p>', $act->description);

        // Both revert rows exist in history, and nothing was deleted.
        $this->assertSame(2, Revision::query()->where('origin', RevisionOrigin::Revert)->count());
        $this->assertNotNull($versionB->fresh());
        $this->assertNotNull($revisionOfA->fresh());
    }
}
