<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Covers `exports:purge`, the sweep for temporary export files whose download
 * never streamed.
 *
 * The command reads `exports.temp_path`, which Tests\TestCase already points at
 * a directory owned by this test — so these tests write their fixtures straight
 * into it and never touch the real storage/app/exports.
 */
class PurgeExportsTest extends TestCase
{
    /**
     * Write a fake leftover export, optionally aged by backdating its mtime.
     */
    private function makeExport(string $name, int $hoursOld = 0): string
    {
        $directory = config('exports.temp_path');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, str_repeat('x', 1024));

        if ($hoursOld > 0) {
            touch($path, now()->subHours($hoursOld)->getTimestamp());
        }

        return $path;
    }

    public function test_it_removes_files_older_than_the_retention_window(): void
    {
        $stale = $this->makeExport('stale.zip', hoursOld: 48);

        $this->artisan('exports:purge')
            ->expectsOutputToContain('Removed 1 export file(s)')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($stale);
    }

    public function test_it_keeps_files_inside_the_retention_window(): void
    {
        // A fresh file is an export that another request may still be streaming.
        $fresh = $this->makeExport('fresh.zip');

        $this->artisan('exports:purge')
            ->expectsOutputToContain('Removed 0 export file(s)')
            ->assertSuccessful();

        $this->assertFileExists($fresh);
    }

    public function test_the_hours_option_overrides_the_configured_window(): void
    {
        $recent = $this->makeExport('recent.epub', hoursOld: 2);

        $this->artisan('exports:purge', ['--hours' => 1])
            ->expectsOutputToContain('older than 1h')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($recent);
    }

    public function test_a_dry_run_reports_without_deleting(): void
    {
        $stale = $this->makeExport('stale.zip', hoursOld: 48);

        $this->artisan('exports:purge', ['--dry-run' => true])
            ->expectsOutputToContain('Would remove 1 export file(s)')
            ->assertSuccessful();

        $this->assertFileExists($stale);
    }

    public function test_it_leaves_subdirectories_alone(): void
    {
        $directory = config('exports.temp_path');
        mkdir($directory.DIRECTORY_SEPARATOR.'keep-me', 0755, true);
        touch($directory.DIRECTORY_SEPARATOR.'keep-me', now()->subHours(48)->getTimestamp());

        $this->artisan('exports:purge')->assertSuccessful();

        $this->assertDirectoryExists($directory.DIRECTORY_SEPARATOR.'keep-me');

        rmdir($directory.DIRECTORY_SEPARATOR.'keep-me');
    }

    public function test_it_succeeds_when_the_directory_does_not_exist(): void
    {
        $this->artisan('exports:purge')
            ->expectsOutputToContain('nothing to purge')
            ->assertSuccessful();
    }

    public function test_it_rejects_a_negative_window(): void
    {
        $this->artisan('exports:purge', ['--hours' => -1])
            ->expectsOutputToContain('cannot be negative')
            ->assertFailed();
    }
}
