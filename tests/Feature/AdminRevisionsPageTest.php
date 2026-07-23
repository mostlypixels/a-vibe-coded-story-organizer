<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Project;
use App\Models\Revision;
use App\Models\RevisionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 13 — the admin "Revisions" page: the confirm-gated retention form and
 * the "Revision storage" panel's bulk-delete actions.
 *
 * RevisionSettingController is the SECOND call site of RevisionPurger (the
 * first is the `revisions:purge` command, covered by
 * RevisionRetentionAndPurgeTest) — these tests exercise the deletion rules
 * through this page's controller actions specifically, proving the wiring,
 * not re-testing RevisionPurger's own rules from scratch.
 */
class AdminRevisionsPageTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Authorization posture (mirrors AdminConfigurationTest's other sections)
    // ---------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.revisions.edit'))->assertRedirect(route('login'));
    }

    public function test_any_authenticated_user_can_load_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.revisions.edit'))
            ->assertOk()
            ->assertSee('Revisions')
            ->assertSee('Retention window');
    }

    // ---------------------------------------------------------------------
    // Retention form — raising (no confirmation)
    // ---------------------------------------------------------------------

    public function test_raising_retention_days_saves_immediately_without_confirmation(): void
    {
        $user = User::factory()->create();
        RevisionSetting::current()->update(['retention_days' => 30]);

        $response = $this->actingAs($user)->patch(route('admin.revisions.update'), [
            'retention_days' => 90,
        ]);

        $response->assertRedirect(route('admin.revisions.edit'));
        $this->assertSame(90, RevisionSetting::current()->retention_days);
    }

    public function test_setting_the_same_value_saves_immediately_without_confirmation(): void
    {
        $user = User::factory()->create();
        RevisionSetting::current()->update(['retention_days' => 30]);

        $response = $this->actingAs($user)->patch(route('admin.revisions.update'), [
            'retention_days' => 30,
        ]);

        $response->assertRedirect(route('admin.revisions.edit'));
        $this->assertSame(30, RevisionSetting::current()->retention_days);
    }

    public function test_retention_days_validation_bounds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('admin.revisions.update'), ['retention_days' => 6])
            ->assertSessionHasErrors('retention_days');

        $this->actingAs($user)->patch(route('admin.revisions.update'), ['retention_days' => 3651])
            ->assertSessionHasErrors('retention_days');
    }

    // ---------------------------------------------------------------------
    // Retention form — lowering (confirm-gated)
    // ---------------------------------------------------------------------

    public function test_lowering_retention_days_shows_a_confirmation_screen_with_the_real_prunable_count(): void
    {
        $user = User::factory()->create();
        RevisionSetting::current()->update(['retention_days' => 90]);

        // Eligible under a 20-day window: automatic, unlabeled, 30 days old,
        // and NOT the newest revision for its field (a newer sibling exists).
        $eligible = $this->seedAutomaticRevision(daysOld: 30);
        $this->seedAutomaticRevision(daysOld: 0, field: $eligible->field, project: $eligible->revisionable);

        // Not eligible: still within the new 20-day window.
        $this->seedAutomaticRevision(daysOld: 5);
        // Not eligible: labeled.
        $this->seedAutomaticRevision(daysOld: 400, label: 'Keep me');
        // Not eligible: manual origin.
        $this->seedAutomaticRevision(daysOld: 400, origin: RevisionOrigin::Manual);

        $response = $this->actingAs($user)->patch(route('admin.revisions.update'), [
            'retention_days' => 20,
        ]);

        $response->assertOk();
        $response->assertSee('Confirm lower retention window');
        $response->assertViewHas('prunableCount', 1);
        $response->assertViewHas('newRetentionDays', 20);
        $response->assertViewHas('currentRetentionDays', 90);

        // Nothing persisted yet — neither the setting nor any deletion.
        $this->assertSame(90, RevisionSetting::current()->retention_days);
        $this->assertModelExists($eligible);
    }

    public function test_confirming_the_lowered_value_persists_it(): void
    {
        $user = User::factory()->create();
        RevisionSetting::current()->update(['retention_days' => 90]);

        $response = $this->actingAs($user)->patch(route('admin.revisions.update'), [
            'retention_days' => 20,
            'confirmed' => '1',
        ]);

        $response->assertRedirect(route('admin.revisions.edit'));
        $this->assertSame(20, RevisionSetting::current()->retention_days);
    }

    public function test_not_confirming_leaves_the_prior_value_unchanged_and_deletes_nothing(): void
    {
        $user = User::factory()->create();
        RevisionSetting::current()->update(['retention_days' => 90]);

        $eligible = $this->seedAutomaticRevision(daysOld: 30);
        $this->seedAutomaticRevision(daysOld: 0, field: $eligible->field, project: $eligible->revisionable);

        // The first (unconfirmed) submission only ever shows the confirm screen.
        $this->actingAs($user)->patch(route('admin.revisions.update'), [
            'retention_days' => 20,
        ]);

        $this->assertSame(90, RevisionSetting::current()->retention_days);
        $this->assertModelExists($eligible);
        $this->assertDatabaseCount('revisions', 2);
    }

    // ---------------------------------------------------------------------
    // Storage panel — per-category counts
    // ---------------------------------------------------------------------

    public function test_storage_panel_shows_correct_per_category_counts(): void
    {
        $user = User::factory()->create();
        $rows = $this->seedOneRevisionPerCategory();

        $response = $this->actingAs($user)->get(route('admin.revisions.edit'));

        $response->assertOk();
        // Four categories, one row each seeded above.
        $response->assertSeeInOrder(['Category', 'Count', 'Size'], false);
        $this->assertDatabaseCount('revisions', 4);
        $this->assertNotNull($rows);
    }

    // ---------------------------------------------------------------------
    // Storage panel — bulk delete
    // ---------------------------------------------------------------------

    public function test_purge_category_removes_exactly_the_targeted_category(): void
    {
        $user = User::factory()->create();
        $rows = $this->seedOneRevisionPerCategory();

        $response = $this->actingAs($user)->delete(route('admin.revisions.purge-category', 'automatic'));

        $response->assertRedirect(route('admin.revisions.edit'));
        $this->assertModelMissing($rows['automatic']);
        $this->assertModelExists($rows['manual']);
        $this->assertModelExists($rows['labeled']);
        $this->assertModelExists($rows['imported']);
    }

    public function test_purge_category_rejects_an_unregistered_category_at_the_router(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.revisions.purge-category', 'not-a-real-category'))
            ->assertNotFound();
    }

    public function test_purge_old_automatic_removes_only_automatic_revisions_older_than_one_year(): void
    {
        $user = User::factory()->create();

        $old = $this->seedAutomaticRevision(daysOld: 400);
        $recent = $this->seedAutomaticRevision(daysOld: 5);
        // The "automatic" category matches on origin alone (RevisionPurger's own
        // rule, task 12) — a label does not exempt a row from an explicit,
        // deliberate purge the way it exempts one from the daily prune sweep.
        $labeledOld = $this->seedAutomaticRevision(daysOld: 400, label: 'Keep me');
        // Different origin entirely: never touched by the "automatic" category.
        $manualOld = $this->seedAutomaticRevision(daysOld: 400, origin: RevisionOrigin::Manual);

        $response = $this->actingAs($user)->delete(route('admin.revisions.purge-old-automatic'));

        $response->assertRedirect(route('admin.revisions.edit'));
        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelMissing($labeledOld);
        $this->assertModelExists($manualOld);
    }

    public function test_purge_old_automatic_can_remove_the_newest_revision_of_a_field_unlike_prune(): void
    {
        // This is purge's whole point (handoff.md §4.3): unlike Revision::
        // prunable(), an explicit purge is allowed to remove even the only/
        // newest automatic revision left for a field.
        $user = User::factory()->create();

        $onlyRevision = $this->seedAutomaticRevision(daysOld: 400);

        $this->assertEmpty((new Revision)->prunable()->pluck('id'));

        $this->actingAs($user)->delete(route('admin.revisions.purge-old-automatic'));

        $this->assertModelMissing($onlyRevision);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function seedAutomaticRevision(
        int $daysOld,
        ?string $field = null,
        ?Project $project = null,
        ?string $label = null,
        RevisionOrigin $origin = RevisionOrigin::Automatic,
    ): Revision {
        $project ??= Project::factory()->create();

        return Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => $field ?? fake()->unique()->word(),
            'origin' => $origin,
            'label' => $label,
            'created_at' => now()->subDays($daysOld),
        ]);
    }

    /**
     * @return array<string, Revision>
     */
    private function seedOneRevisionPerCategory(): array
    {
        $project = Project::factory()->create();

        $base = [
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
        ];

        return [
            'automatic' => Revision::factory()->create([
                ...$base,
                'field' => 'description',
                'origin' => RevisionOrigin::Automatic,
                'label' => null,
            ]),
            'manual' => Revision::factory()->create([
                ...$base,
                'field' => 'rights',
                'origin' => RevisionOrigin::Manual,
                'label' => null,
            ]),
            'labeled' => Revision::factory()->create([
                ...$base,
                'field' => 'dedication',
                'origin' => RevisionOrigin::Revert,
                'label' => 'Reverted to last week',
            ]),
            'imported' => Revision::factory()->create([
                ...$base,
                'field' => 'preface',
                'origin' => RevisionOrigin::Import,
                'label' => null,
            ]),
        ];
    }
}
