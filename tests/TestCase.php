<?php

namespace Tests;

use App\Models\Book;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    private string $exportTempPath;

    /** Give each parallel test an isolated export directory. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->exportTempPath = storage_path('framework/testing/exports/'.Str::random(16));

        config(['exports.temp_path' => $this->exportTempPath]);
    }

    protected function tearDown(): void
    {
        $this->removeExportArtifacts();

        parent::tearDown();
    }

    /**
     * A project and the book Project::created makes with it, for the many tests
     * that only need somewhere to put a manuscript. A test that is *about* book
     * structure builds its books explicitly instead.
     *
     * @return array{0: Project, 1: Book}
     */
    protected function projectWithBook(?User $owner = null): array
    {
        $project = Project::factory()->for($owner ?? User::factory())->create();

        return [$project, $project->books()->first()];
    }

    /**
     * Remove the test's export directory. Exports are flat uuid-named files, so
     * one pass over the directory is enough.
     */
    private function removeExportArtifacts(): void
    {
        // setUp can fail before the property is set; there is nothing to remove then.
        if (! isset($this->exportTempPath) || ! is_dir($this->exportTempPath)) {
            return;
        }

        foreach ((array) glob($this->exportTempPath.DIRECTORY_SEPARATOR.'*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->exportTempPath);
    }
}
