<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uploaded covers and codex media are private files. Only the project owner can read them. */
class MediaFileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Storage::fake('public');
    }

    public function test_owner_gets_the_project_cover(): void
    {
        $project = Project::factory()->create(['cover_image' => 'project-covers/a.png']);
        Storage::disk('media')->put('project-covers/a.png', 'cover bytes');

        $response = $this->actingAs($project->user)->get(route('projects.cover', $project));

        $response->assertOk();
        $this->assertSame('cover bytes', $response->streamedContent());
    }

    public function test_owner_gets_the_book_cover(): void
    {
        $book = Book::factory()->create(['cover_image' => 'book-covers/a.png']);
        Storage::disk('media')->put('book-covers/a.png', 'book bytes');

        $response = $this->actingAs($book->project->user)->get(route('books.cover', $book));

        $response->assertOk();
        $this->assertSame('book bytes', $response->streamedContent());
    }

    public function test_owner_gets_the_chapter_cover(): void
    {
        $chapter = Chapter::factory()->create(['cover_image' => 'chapter-covers/a.png']);
        Storage::disk('media')->put('chapter-covers/a.png', 'chapter bytes');

        $response = $this->actingAs($chapter->revisionProject()->user)->get(route('chapters.cover', $chapter));

        $response->assertOk();
        $this->assertSame('chapter bytes', $response->streamedContent());
    }

    public function test_owner_gets_a_codex_media_file(): void
    {
        $media = CodexMedia::factory()->referenceFile()->create();
        Storage::disk('media')->put($media->path, 'pdf bytes');

        $response = $this->actingAs($media->entry->project->user)->get(route('codex-media.show', $media));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('pdf bytes', $response->streamedContent());
    }

    public function test_a_non_owner_gets_403_for_every_media_route(): void
    {
        $stranger = User::factory()->create();

        foreach ($this->mediaUrls() as $url) {
            $this->actingAs($stranger)->get($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login_for_every_media_route(): void
    {
        foreach ($this->mediaUrls() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_a_missing_file_gives_404(): void
    {
        $project = Project::factory()->create(['cover_image' => null]);
        $media = CodexMedia::factory()->referenceFile()->create(['path' => null]);

        $this->actingAs($project->user)->get(route('projects.cover', $project))->assertNotFound();
        $this->actingAs($media->entry->project->user)->get(route('codex-media.show', $media))->assertNotFound();
    }

    public function test_the_media_disk_has_no_public_url(): void
    {
        $this->assertArrayNotHasKey('url', config('filesystems.disks.media'));
        $this->assertStringNotContainsString(public_path(), config('filesystems.disks.media.root'));
    }

    public function test_the_move_command_moves_old_public_files_to_the_media_disk(): void
    {
        Storage::disk('public')->put('project-covers/old.png', 'old cover');
        Storage::disk('public')->put('codex-media/old.pdf', 'old file');
        Storage::disk('public')->put('unrelated/keep.txt', 'not media');

        $this->artisan('media:move-to-private')->assertSuccessful();

        Storage::disk('media')->assertExists(['project-covers/old.png', 'codex-media/old.pdf']);
        Storage::disk('public')->assertMissing(['project-covers/old.png', 'codex-media/old.pdf']);
        Storage::disk('public')->assertExists('unrelated/keep.txt');
        $this->assertSame('old cover', Storage::disk('media')->get('project-covers/old.png'));
    }

    /** @return list<string> */
    private function mediaUrls(): array
    {
        $project = Project::factory()->create(['cover_image' => 'project-covers/a.png']);
        $book = Book::factory()->create(['cover_image' => 'book-covers/a.png']);
        $chapter = Chapter::factory()->create(['cover_image' => 'chapter-covers/a.png']);
        $media = CodexMedia::factory()->create();

        foreach (['project-covers/a.png', 'book-covers/a.png', 'chapter-covers/a.png', $media->path] as $path) {
            Storage::disk('media')->put($path, 'bytes');
        }

        return [
            route('projects.cover', $project),
            route('books.cover', $book),
            route('chapters.cover', $chapter),
            route('codex-media.show', $media),
        ];
    }
}
