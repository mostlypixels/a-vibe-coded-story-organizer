<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Act;
use App\Models\Project;
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
 * (task 16): copies an older revision's value back onto the live column,
 * additively (expanded/architecture.md "Revert"). Never
 * destructive: the reverted-away-from state and every other row stay exactly as
 * they were, and revert always creates a new `origin: revert` row rather than
 * editing anything.
 *
 * The service is covered through the HTTP endpoint rather than in isolation —
 * the base-hash check, the re-validation and the recorded row are all things a
 * request has to end up with, and testing them here keeps the conflict *response*
 * (task 16's changed behaviour) in the same file as the behaviour it changed.
 */
class RevertRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function actFor(User $user, array $overrides = []): Act
    {
        $project = Project::factory()->for($user)->create();

        return Act::factory()->for($project)->create($overrides);
    }

    private function revisionFor(Act $act, array $overrides = []): Revision
    {
        return Revision::factory()->create(array_merge([
            'revisionable_type' => Act::class,
            'revisionable_id' => $act->id,
            'project_id' => $act->project->id,
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
     * Task 16 changed this deliberately: a conflict used to abort(409) into a
     * bare error page shown to a writer who did nothing wrong. It now redirects
     * back with an error alert they can act on. See resolution-log.md.
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
     * standing-issues.md #4: every conflict test above asserts the app *decided*
     * to flash a message. None asserted any page renders it — so the alert could
     * have been wired into the wrong shell, or given the wrong props, and the
     * whole suite would still have passed with the feature exactly as broken as
     * it was before task 16.
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
        // standing-issues.md #6: the message names the field that moved — a
        // compare page shows several at once, each with its own revert button —
        // and no longer tells the writer to reload, which is a step the redirect
        // has already taken for them.
        $response->assertSee('"Description" changed somewhere else while this page was open');
        $response->assertDontSee('reload and try again');
    }

    /**
     * standing-issues.md #5: the base hash was checked against the model already
     * hydrated in memory, and the value was written afterwards — two steps with
     * nothing holding the row still in between. Two reverts arriving together
     * could both pass the check, and the second would silently overwrite the
     * first: exactly the outcome the base hash exists to prevent.
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
     * standing-issues.md #1: reverting re-validates the old value against
     * *today's* rules, which is correct — rules can have tightened since it was
     * recorded, and an old value must not reach the column through a door a
     * normal save would have closed. But the resulting message went into
     * $errors, which no revisions view rendered: the page came back looking
     * identical, with no message, no change, and no explanation.
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
     * standing-issues.md #2: `restore()` changed the column and recorded the
     * change as two separate statements outside any transaction. If the second
     * failed, the value moved with nothing in the history saying so — the one
     * outcome this whole feature exists to prevent.
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
            'project_id' => $act->project->id,
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
