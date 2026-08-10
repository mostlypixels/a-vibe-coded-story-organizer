<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * The per-test export directory (see {@see setUp()}).
     */
    private string $exportTempPath;

    /**
     * Point the exporters at a directory this test owns.
     *
     * An export writes a real file, and only a streamed download deletes it
     * (BinaryFileResponse::deleteFileAfterSend). A test asserts on the response
     * instead of sending it, so the file stays behind. Cleanup here is
     * automatic on purpose: the same cleanup was once each test's job to
     * remember, and the shared directory grew to thousands of files.
     *
     * Each test gets its OWN directory because the suite runs in parallel —
     * processes that shared one directory could delete an export another
     * process was still writing.
     */
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
