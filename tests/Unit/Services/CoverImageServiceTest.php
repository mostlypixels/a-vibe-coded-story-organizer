<?php

namespace Tests\Unit\Services;

use App\Services\CoverImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unit tests for the CoverImageService.
 */
class CoverImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoverImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = app(CoverImageService::class);
    }

    public function test_store_returns_a_path_under_the_given_directory(): void
    {
        $file = UploadedFile::fake()->image('cover.jpg');

        $path = $this->service->store($file, 'project-covers');

        $this->assertStringStartsWith('project-covers/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_store_preserves_the_file_extension(): void
    {
        $file = UploadedFile::fake()->image('cover.png');

        $path = $this->service->store($file, 'project-covers');

        $this->assertStringEndsWith('.png', $path);
    }

    public function test_delete_is_null_safe(): void
    {
        // Should not throw when passed null.
        $this->service->delete(null);
        $this->assertTrue(true);
    }

    public function test_delete_removes_an_existing_file(): void
    {
        $path = 'project-covers/test-cover.jpg';
        Storage::disk('public')->put($path, 'test contents');

        $this->service->delete($path);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_bytes_returns_null_when_path_is_null(): void
    {
        $result = $this->service->bytes(null);

        $this->assertNull($result);
    }

    public function test_bytes_returns_null_when_file_does_not_exist(): void
    {
        $result = $this->service->bytes('project-covers/nonexistent.jpg');

        $this->assertNull($result);
    }

    public function test_bytes_returns_the_file_contents(): void
    {
        $path = 'project-covers/test-cover.jpg';
        $contents = 'test file contents';
        Storage::disk('public')->put($path, $contents);

        $result = $this->service->bytes($path);

        $this->assertSame($contents, $result);
    }

    public function test_mime_type_returns_null_when_path_is_null(): void
    {
        $result = $this->service->mimeType(null);

        $this->assertNull($result);
    }

    public function test_mime_type_returns_null_when_file_does_not_exist(): void
    {
        $result = $this->service->mimeType('project-covers/nonexistent.jpg');

        $this->assertNull($result);
    }

    public function test_mime_type_returns_the_file_mime_type(): void
    {
        $file = UploadedFile::fake()->image('cover.png');
        $path = $this->service->store($file, 'project-covers');

        $result = $this->service->mimeType($path);

        $this->assertNotNull($result);
        $this->assertStringContainsString('image', $result);
    }
}
