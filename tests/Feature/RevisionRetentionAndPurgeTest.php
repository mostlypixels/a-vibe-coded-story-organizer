<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Project;
use App\Models\Revision;
use App\Models\RevisionSetting;
use App\Services\RevisionPurger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The RevisionSetting singleton, the daily model:prune scheduling,
 * RevisionPurger, and the `revisions:purge` command.
 *
 * Revision::prunable() reads RevisionSetting::current()->retention_days, not
 * the raw config. The prune-vs-purge distinction is the central safety claim:
 * prune (model:prune) never touches a labeled or non-automatic row, while purge
 * (RevisionPurger / revisions:purge) may, when the caller targets that category
 * directly.
 */
class RevisionRetentionAndPurgeTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // RevisionSetting singleton
    // ---------------------------------------------------------------------

    public function test_current_lazily_creates_from_config_on_first_read(): void
    {
        config(['revisions.retention_days' => 42]);

        $this->assertDatabaseCount('revision_settings', 0);

        $setting = RevisionSetting::current();

        $this->assertSame(42, $setting->retention_days);
        $this->assertDatabaseCount('revision_settings', 1);
    }

    public function test_current_returns_the_existing_row_without_creating_a_second_one(): void
    {
        RevisionSetting::current();
        $this->assertDatabaseCount('revision_settings', 1);

        RevisionSetting::current();
        $this->assertDatabaseCount('revision_settings', 1);
    }

    public function test_prunable_reflects_a_changed_retention_setting(): void
    {
        RevisionSetting::current()->update(['retention_days' => 90]);

        $project = Project::factory()->create();

        $revision = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(30),
        ]);
        // A newer sibling so the 30-day-old row above is not "the only/newest"
        // for its field, which would otherwise exempt it regardless of age.
        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now(),
        ]);

        // Still within the (default 90-day) retention window.
        $this->assertEmpty((new Revision)->prunable()->pluck('id'));

        // Lowering the retention window to 20 days makes the 30-day-old row
        // eligible — proving prunable() reads the live setting, not a cached
        // config value captured at boot.
        RevisionSetting::current()->update(['retention_days' => 20]);

        $this->assertSame([$revision->id], (new Revision)->prunable()->pluck('id')->all());
    }

    /**
     * The prune protects "the newest revision for this field" — that is the
     * promise that makes deleting old rows safe at all. Every other query in the
     * feature decides "newest" by `(created_at, id)`.
     *
     * This pins the promise against rows whose timestamp order and insertion
     * order disagree: the surviving row must be the newest one a *reader* would
     * see in the history, not merely the last one written. Baselines are
     * deliberately back-dated to the entity's `updated_at`, so out-of-order
     * timestamps are a shape this table genuinely holds.
     */
    public function test_the_prune_keeps_the_newest_revision_even_when_it_was_inserted_first(): void
    {
        RevisionSetting::current()->update(['retention_days' => 90]);

        $project = Project::factory()->create();

        // Inserted first, so it holds the LOWEST id — but it is the newest by
        // timestamp, and therefore the one the history page shows as current.
        $newest = $this->automaticRevision($project, 'description', now()->subDays(200));

        // Inserted second (higher id), but older: a back-dated row.
        $older = $this->automaticRevision($project, 'description', now()->subDays(300));

        $prunable = (new Revision)->prunable()->pluck('id')->all();

        $this->assertContains($older->id, $prunable, 'the older revision is past retention and should be prunable');
        $this->assertNotContains(
            $newest->id,
            $prunable,
            'the newest revision for a field must never be prunable — losing it is data loss, not tidying',
        );

        $this->artisan('model:prune', ['--model' => [Revision::class]])->assertSuccessful();

        $this->assertModelExists($newest);
        $this->assertModelMissing($older);
    }

    /** An automatic, unlabeled revision on the project's `$field`, created at `$createdAt`. */
    private function automaticRevision(Project $project, string $field, \DateTimeInterface $createdAt): Revision
    {
        return Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => $field,
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => $createdAt,
        ]);
    }

    // ---------------------------------------------------------------------
    // model:prune scheduling
    // ---------------------------------------------------------------------

    public function test_model_prune_command_removes_only_prunable_rows(): void
    {
        RevisionSetting::current()->update(['retention_days' => 90]);
        $project = Project::factory()->create();

        $prunable = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(200),
        ]);
        // Newest sibling for the same field — kept by prunable()'s own rule.
        $newestSibling = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now(),
        ]);
        $labeled = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'rights',
            'origin' => RevisionOrigin::Automatic,
            'label' => 'Keep me',
            'created_at' => now()->subDays(200),
        ]);

        $this->artisan('model:prune', ['--model' => [Revision::class]])
            ->assertSuccessful();

        $this->assertModelMissing($prunable);
        $this->assertModelExists($newestSibling);
        $this->assertModelExists($labeled);
    }

    public function test_model_prune_pretend_removes_nothing_but_reports_a_count(): void
    {
        RevisionSetting::current()->update(['retention_days' => 90]);
        $project = Project::factory()->create();

        $prunable = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(200),
        ]);
        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now(),
        ]);

        $this->artisan('model:prune', ['--model' => [Revision::class], '--pretend' => true])
            ->expectsOutputToContain('1 [App\Models\Revision] records will be pruned')
            ->assertSuccessful();

        $this->assertModelExists($prunable);
    }

    // ---------------------------------------------------------------------
    // RevisionPurger
    // ---------------------------------------------------------------------

    public function test_purger_removes_exactly_the_automatic_category(): void
    {
        $rows = $this->seedOneRevisionPerCategory();

        $result = (new RevisionPurger)->purge(RevisionPurger::CATEGORY_AUTOMATIC);

        $this->assertSame(1, $result->count);
        $this->assertModelMissing($rows['automatic']);
        $this->assertModelExists($rows['manual']);
        $this->assertModelExists($rows['labeled']);
        $this->assertModelExists($rows['imported']);
    }

    public function test_purger_removes_exactly_the_manual_category(): void
    {
        $rows = $this->seedOneRevisionPerCategory();

        $result = (new RevisionPurger)->purge(RevisionPurger::CATEGORY_MANUAL);

        $this->assertSame(1, $result->count);
        $this->assertModelExists($rows['automatic']);
        $this->assertModelMissing($rows['manual']);
        $this->assertModelExists($rows['labeled']);
        $this->assertModelExists($rows['imported']);
    }

    public function test_purger_removes_exactly_the_labeled_category(): void
    {
        $rows = $this->seedOneRevisionPerCategory();

        $result = (new RevisionPurger)->purge(RevisionPurger::CATEGORY_LABELED);

        $this->assertSame(1, $result->count);
        $this->assertModelExists($rows['automatic']);
        $this->assertModelExists($rows['manual']);
        $this->assertModelMissing($rows['labeled']);
        $this->assertModelExists($rows['imported']);
    }

    public function test_purger_removes_exactly_the_imported_category(): void
    {
        $rows = $this->seedOneRevisionPerCategory();

        $result = (new RevisionPurger)->purge(RevisionPurger::CATEGORY_IMPORTED);

        $this->assertSame(1, $result->count);
        $this->assertModelExists($rows['automatic']);
        $this->assertModelExists($rows['manual']);
        $this->assertModelExists($rows['labeled']);
        $this->assertModelMissing($rows['imported']);
    }

    public function test_purger_with_an_age_cutoff_removes_only_older_rows_in_the_category(): void
    {
        $project = Project::factory()->create();

        $old = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Manual,
            'label' => null,
            'created_at' => now()->subDays(400),
        ]);
        $recent = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'rights',
            'origin' => RevisionOrigin::Manual,
            'label' => null,
            'created_at' => now()->subDays(5),
        ]);

        $result = (new RevisionPurger)->purge(
            RevisionPurger::CATEGORY_MANUAL,
            before: now()->subDays(365),
        );

        $this->assertSame(1, $result->count);
        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_purger_dry_run_reports_without_deleting(): void
    {
        $rows = $this->seedOneRevisionPerCategory();

        $result = (new RevisionPurger)->purge(RevisionPurger::CATEGORY_AUTOMATIC, dryRun: true);

        $this->assertSame(1, $result->count);
        $this->assertModelExists($rows['automatic']);
        $this->assertDatabaseCount('revisions', 4);
    }

    public function test_purger_can_remove_a_labeled_or_manual_revision_when_explicitly_targeted(): void
    {
        // This is the test that proves purge does what prune (Revision::
        // prunable()) is forbidden from doing: removing a labeled row and a
        // non-automatic-origin row, when the caller explicitly asks for that
        // category.
        $rows = $this->seedOneRevisionPerCategory();

        // Sanity check first: prune would never touch either of these.
        $this->assertEmpty(
            (new Revision)->prunable()->whereIn('id', [$rows['labeled']->id, $rows['manual']->id])->pluck('id'),
        );

        (new RevisionPurger)->purge(RevisionPurger::CATEGORY_LABELED);
        (new RevisionPurger)->purge(RevisionPurger::CATEGORY_MANUAL);

        $this->assertModelMissing($rows['labeled']);
        $this->assertModelMissing($rows['manual']);
    }

    public function test_purger_rejects_an_unknown_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RevisionPurger)->purge('not-a-real-category');
    }

    // ---------------------------------------------------------------------
    // revisions:purge command
    // ---------------------------------------------------------------------

    public function test_purge_command_dry_run_and_real_run_report_matching_counts(): void
    {
        $this->seedOneRevisionPerCategory();

        $this->artisan('revisions:purge', ['--category' => 'automatic', '--dry-run' => true])
            ->expectsOutputToContain('Would remove 1 revision(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('revisions', 4);

        $this->artisan('revisions:purge', ['--category' => 'automatic'])
            ->expectsOutputToContain('Removed 1 revision(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('revisions', 3);
    }

    public function test_purge_command_requires_a_category(): void
    {
        $this->artisan('revisions:purge')
            ->assertFailed();
    }

    public function test_purge_command_scopes_by_project(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $rowA = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $projectA->id,
            'project_id' => $projectA->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
        ]);
        $rowB = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $projectB->id,
            'project_id' => $projectB->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
        ]);

        $this->artisan('revisions:purge', ['--category' => 'automatic', '--project' => $projectA->id])
            ->assertSuccessful();

        $this->assertModelMissing($rowA);
        $this->assertModelExists($rowB);
    }

    /**
     * Seed exactly one revision in each of RevisionPurger's four categories,
     * on distinct fields so category filters cannot accidentally overlap.
     *
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
