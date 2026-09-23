<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Services\CodexMediaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends covers and codex media from the private `media` disk.
 *
 * The files have no public URL. Each action walks up to the owning project and
 * checks ProjectPolicy@view first. Exports read the disk directly and do not use
 * these routes.
 */
class MediaFileController extends Controller
{
    public function projectCover(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        return $this->send($project->cover_image);
    }

    public function bookCover(Book $book): StreamedResponse
    {
        $this->authorize('view', $book->project);

        return $this->send($book->cover_image);
    }

    public function chapterCover(Chapter $chapter): StreamedResponse
    {
        $this->authorize('view', $chapter->revisionProject());

        return $this->send($chapter->cover_image);
    }

    public function codexMedia(CodexMedia $codexMedia): StreamedResponse
    {
        $this->authorize('view', $codexMedia->entry->project);

        return $this->send($codexMedia->path, $codexMedia->original_name);
    }

    private function send(?string $path, ?string $name = null): StreamedResponse
    {
        $disk = Storage::disk(CodexMediaService::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        // The browser must use the stored type. It must not guess a type that runs script.
        return $disk->response($path, $name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
