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
 * Task 01 — data model foundation: the imports/import_settings tables, their
 * models, the ImportPhase enum, and ImportPolicy. Pure model/policy tests, no
 * end-to-end import (that's later tasks).
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
            'import.default_run_in_background' => false,
        ]);

        $this->assertSame(0, ImportSetting::count());

        $setting = ImportSetting::current();

        $this->assertSame(204800, $setting->max_archive_kilobytes);
        $this->assertFalse($setting->run_in_background);
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

    public function test_run_in_background_is_cast_to_a_real_boolean(): void
    {
        $setting = ImportSetting::current();
        $setting->update(['run_in_background' => 1]);

        $fresh = $setting->fresh();
        $this->assertIsBool($fresh->run_in_background);
        $this->assertTrue($fresh->run_in_background);
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
}
