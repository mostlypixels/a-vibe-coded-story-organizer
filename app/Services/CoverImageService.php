<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Manages project and chapter cover image files on the public disk.
 *
 * Provides a single source for storing, deleting, and reading cover files
 * — used by the project editor, chapter editor (task 07), and the epub exporter.
 */
class CoverImageService
{
    /**
     * The disk and directory the cover images live on. Mirrors CodexMediaService::DISK
     * ('public') so covers are reachable at /storage/... once `php artisan storage:link`
     * has run.
     */
    public const COVER_DISK = 'public';

    /**
     * The directory under the public disk where project covers are stored.
     */
    public const PROJECT_COVER_DIRECTORY = 'project-covers';

    /**
     * The directory under the public disk where chapter covers are stored (task 07).
     */
    public const CHAPTER_COVER_DIRECTORY = 'chapter-covers';

    /**
     * Store an already-validated cover upload on the public disk and return its path.
     *
     * A single path column (no tracking row), so this is intentionally thinner than
     * CodexMediaService::store().
     *
     * @param  string  $directory  The directory under the public disk (e.g., 'project-covers').
     */
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::COVER_DISK);
    }

    /**
     * Copy an already-validated cover file from an extracted import archive onto the
     * public disk at a freshly generated path, returning that path. Mirrors
     * CodexMediaService::storeImportedFile(): the archive's own relative path is never
     * reused, and the disk/naming knowledge stays here so the importer never learns
     * where covers live.
     *
     * @param  string  $directory  The directory under the public disk (e.g., 'chapter-covers').
     */
    public function storeImportedFile(string $absolutePath, string $directory): string
    {
        $path = Storage::disk(self::COVER_DISK)->putFile($directory, new File($absolutePath));

        if ($path === false) {
            throw new RuntimeException("Unable to copy the imported cover file \"{$absolutePath}\" to storage.");
        }

        return $path;
    }

    /**
     * Delete a cover file off the public disk when there is one, so a replaced or
     * removed cover never lingers as an orphan. Null-safe: deleting a null path is a no-op.
     */
    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::COVER_DISK)->delete($path);
        }
    }

    /**
     * Read the raw bytes of a cover file off the public disk for embedding in exports
     * (like the epub). Returns null if the path is null or the file does not exist.
     */
    public function bytes(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (! Storage::disk(self::COVER_DISK)->exists($path)) {
            return null;
        }

        $bytes = Storage::disk(self::COVER_DISK)->get($path);

        return $bytes ?? null;
    }

    /**
     * Read the MIME type of a cover file off the public disk. Returns null if the path
     * is null or the file does not exist.
     */
    public function mimeType(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (! Storage::disk(self::COVER_DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::COVER_DISK)->mimeType($path) ?: null;
    }
}
