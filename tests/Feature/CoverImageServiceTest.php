<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\CoverImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CoverImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_save_deletes_the_new_file_and_keeps_the_old_one(): void
    {
        Storage::fake('media');
        $oldPath = 'book-covers/old-cover.jpg';
        Storage::disk('media')->put($oldPath, 'contents');
        $book = Book::factory()->create(['cover_image' => $oldPath]);

        Book::saving(fn () => throw new RuntimeException('Save failed.'));

        try {
            app(CoverImageService::class)->saveWithCover(
                $book,
                UploadedFile::fake()->image('new-cover.jpg'),
                false,
                CoverImageService::BOOK_COVER_DIRECTORY,
            );
            $this->fail('The save did not throw.');
        } catch (RuntimeException) {
        }

        Storage::disk('media')->assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::disk('media')->allFiles());
        $this->assertSame($oldPath, $book->fresh()->cover_image);
    }
}
