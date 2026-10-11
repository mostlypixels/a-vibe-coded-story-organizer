<?php

namespace Tests\Feature;

use App\Enums\ImportPhase;
use App\Models\Import;
use App\Models\ImportSetting;
use App\Models\Project;
use App\Models\User;
use App\Policies\ImportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The import data model: the imports/import_settings tables, their models, the
 * ImportPhase enum, and ImportPolicy. Model and policy tests only —
 * ImportRoundTripTest covers the end-to-end import.
 */
class ImportDataModelTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // ImportSetting — singleton
    // ---------------------------------------------------------------------

    public function test_current_lazily_creates_from_config_defaults_on_a_fresh_install(): void
    {
        config([
            'import.default_max_archive_kilobytes' => 204800,
        ]);

        $this->assertSame(0, ImportSetting::count());

        $setting = ImportSetting::current();

        $this->assertSame(204800, $setting->max_archive_kilobytes);
        $this->assertSame(1, ImportSetting::count());
    }

    public function test_current_returns_the_existing_row_and_never_creates_a_duplicate(): void
    {
        $first = ImportSetting::current();
        ImportSetting::current();
        $again = ImportSetting::current();

        $this->assertSame(1, ImportSetting::count());
        $this->assertSame($first->id, $again->id);
    }

    // ---------------------------------------------------------------------
    // Import — factory & casts
    // ---------------------------------------------------------------------

    public function test_factory_produces_a_valid_row_at_each_phase(): void
    {
        foreach (ImportPhase::cases() as $phase) {
            $import = Import::factory()->phase($phase)->create();

            $this->assertInstanceOf(ImportPhase::class, $import->fresh()->phase);
            $this->assertSame($phase, $import->fresh()->phase);
        }

        $this->assertSame(count(ImportPhase::cases()), Import::count());
    }

    public function test_id_maps_casts_round_trip_as_an_array(): void
    {
        $maps = ['actIdMap' => [1 => 10, 2 => 11], 'eventIdMap' => [5 => 50]];

        $import = Import::factory()->create(['id_maps' => $maps]);

        $fresh = $import->fresh();
        $this->assertIsArray($fresh->id_maps);
        $this->assertSame($maps, $fresh->id_maps);
    }

    public function test_import_belongs_to_a_user_and_optionally_a_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $import = Import::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $this->assertTrue($import->user->is($user));
        $this->assertTrue($import->project->is($project));

        $pending = Import::factory()->create(['project_id' => null]);
        $this->assertNull($pending->project);
    }

    // ---------------------------------------------------------------------
    // ImportPolicy — real ownership check
    // ---------------------------------------------------------------------

    public function test_owner_may_resume_and_discard_their_own_import(): void
    {
        $owner = User::factory()->create();
        $import = Import::factory()->create(['user_id' => $owner->id]);

        $policy = new ImportPolicy;

        $this->assertTrue($policy->resume($owner, $import));
        $this->assertTrue($policy->discard($owner, $import));
    }

    public function test_a_different_user_may_not_resume_or_discard_the_import(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $import = Import::factory()->create(['user_id' => $owner->id]);

        $policy = new ImportPolicy;

        $this->assertFalse($policy->resume($stranger, $import));
        $this->assertFalse($policy->discard($stranger, $import));
    }

    // ---------------------------------------------------------------------
    // FK delete behavior
    // ---------------------------------------------------------------------

    public function test_deleting_the_referenced_project_leaves_the_import_intact_with_null_project_id(): void
    {
        $project = Project::factory()->create();
        $import = Import::factory()->create(['project_id' => $project->id]);

        $project->delete();

        $fresh = $import->fresh();
        $this->assertNotNull($fresh, 'Import row must survive its project being deleted.');
        $this->assertNull($fresh->project_id);
    }
    // ---------------------------------------------------------------------
    // Run state
    // ---------------------------------------------------------------------

    public function test_is_running_is_true_only_for_a_fresh_claim(): void
    {
        config(['import.timeout_seconds' => 1800]);

        $this->assertFalse(Import::factory()->make()->isRunning());
        $this->assertTrue(Import::factory()->make(['running_since' => now()->subMinute()])->isRunning());

        // An older claim is a crashed run.
        $this->assertFalse(Import::factory()->make(['running_since' => now()->subSeconds(1801)])->isRunning());
    }

    public function test_is_queued_needs_a_queue_time_no_fresh_claim_and_no_terminal_phase(): void
    {
        $this->assertFalse(Import::factory()->make()->isQueued());
        $this->assertTrue(Import::factory()->make(['queued_at' => now()])->isQueued());
        $this->assertTrue(Import::factory()->phase(ImportPhase::Story)->make(['queued_at' => now()])->isQueued());

        $this->assertFalse(Import::factory()->make(['queued_at' => now(), 'running_since' => now()])->isQueued());
        $this->assertFalse(Import::factory()->phase(ImportPhase::Completed)->make(['queued_at' => now()])->isQueued());
        $this->assertFalse(Import::factory()->phase(ImportPhase::Failed)->make(['queued_at' => now()])->isQueued());
    }
}
