<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Project;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RevisionController::revert() and the App\Services\RevisionReverter behind it
 * (task 16): copies an older revision's value back onto the live column,
 * additively (expanded/architecture.md "Revert", handoff.md §5.2). Never
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
        $response->assertSessionHas('error');
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
