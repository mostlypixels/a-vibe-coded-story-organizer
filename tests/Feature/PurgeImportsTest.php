<?php

namespace Tests\Feature;

use App\Enums\ImportPhase;
use App\Models\Import;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers `imports:purge`, the sweep for import working files that no resume
 * or discard will remove.
 */
class PurgeImportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Write the ZIP and extracted folder for one import, aged by their mtime.
     */
    private function makeWorkingFiles(string $uuid, int $daysOld = 0): string
    {
        $disk = Storage::disk('local');
        $archivePath = "imports/{$uuid}.zip";
        $disk->put($archivePath, 'zip');
        $disk->put("imports/{$uuid}/data/manifest.json", '{}');

        $timestamp = now()->subDays($daysOld)->getTimestamp();
        touch($disk->path($archivePath), $timestamp);
        touch($disk->path("imports/{$uuid}"), $timestamp);

        return $archivePath;
    }

    private function makeImport(string $uuid, int $daysOld, ImportPhase $phase = ImportPhase::Pending): Import
    {
        return Import::factory()->phase($phase)->create([
            'archive_path' => $this->makeWorkingFiles($uuid, $daysOld),
            'updated_at' => now()->subDays($daysOld),
        ]);
    }

    public function test_it_removes_a_stale_pending_import_and_its_files(): void
    {
        $import = $this->makeImport('stale', daysOld: 30);

        $this->artisan('imports:purge')->assertSuccessful();

        $this->assertModelMissing($import);
        Storage::disk('local')->assertMissing('imports/stale.zip');
        Storage::disk('local')->assertMissing('imports/stale');
    }

    public function test_it_keeps_an_import_inside_the_retention_window(): void
    {
        // A recent import may still run or be resumed.
        $import = $this->makeImport('fresh', daysOld: 1);

        $this->artisan('imports:purge')->assertSuccessful();

        $this->assertModelExists($import);
        Storage::disk('local')->assertExists('imports/fresh.zip');
        Storage::disk('local')->assertExists('imports/fresh/data/manifest.json');
    }

    public function test_it_keeps_the_partial_project_of_a_stale_stalled_import(): void
    {
        $project = Project::factory()->create();
        $import = Import::factory()->phase(ImportPhase::Story)->for($project->user)->for($project)->create([
            'archive_path' => $this->makeWorkingFiles('stalled', daysOld: 30),
            'updated_at' => now()->subDays(30),
        ]);

        $this->artisan('imports:purge')->assertSuccessful();

        $this->assertModelMissing($import);
        $this->assertModelExists($project);
        Storage::disk('local')->assertMissing('imports/stalled.zip');
    }

    public function test_it_keeps_a_stale_completed_import_row(): void
    {
        $import = Import::factory()->phase(ImportPhase::Completed)->create([
            'updated_at' => now()->subDays(30),
        ]);

        $this->artisan('imports:purge')->assertSuccessful();

        $this->assertModelExists($import);
    }

    public function test_it_removes_old_working_files_that_have_no_import_row(): void
    {
        // Account deletion drops import rows at the database level.
        $this->makeWorkingFiles('orphan', daysOld: 30);

        $this->artisan('imports:purge')->assertSuccessful();

        Storage::disk('local')->assertMissing('imports/orphan.zip');
        Storage::disk('local')->assertMissing('imports/orphan');
    }

    public function test_it_keeps_new_working_files_that_have_no_import_row_yet(): void
    {
        // An upload writes its files before it creates the import row.
        $this->makeWorkingFiles('uploading');

        $this->artisan('imports:purge')->assertSuccessful();

        Storage::disk('local')->assertExists('imports/uploading.zip');
        Storage::disk('local')->assertExists('imports/uploading/data/manifest.json');
    }

    public function test_the_days_option_overrides_the_configured_window(): void
    {
        $import = $this->makeImport('recent', daysOld: 2);

        $this->artisan('imports:purge', ['--days' => 1])->assertSuccessful();

        $this->assertModelMissing($import);
        Storage::disk('local')->assertMissing('imports/recent.zip');
    }

    public function test_it_succeeds_when_the_directory_does_not_exist(): void
    {
        $this->artisan('imports:purge')
            ->expectsOutputToContain('Removed 0 stale import(s)')
            ->assertSuccessful();
    }

    public function test_it_rejects_a_negative_window(): void
    {
        $this->artisan('imports:purge', ['--days' => -1])
            ->expectsOutputToContain('cannot be negative')
            ->assertFailed();
    }
}
