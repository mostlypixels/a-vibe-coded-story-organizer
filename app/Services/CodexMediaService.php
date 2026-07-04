<?php

namespace App\Services;

use App\Enums\CodexMediaCollection;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Owns everything about Codex media files: where they live on disk, how they are
 * named, the single-cover rule, and cleaning files off disk on every removal path.
 *
 * Controllers hand it already-validated uploads (the Form Requests enforce mime/size);
 * nothing about storage paths or the single-cover rule is duplicated in a controller
 * (guidelines: keep configuration/workflow in one place, thin controllers).
 */
class CodexMediaService
{
    /**
     * The disk media is stored on. `public` + `php artisan storage:link` makes files
     * reachable at /storage/... (v1 accepts publicly reachable URLs).
     */
    private const DISK = 'public';

    /**
     * Directory (relative to the disk root) all Codex media is stored under.
     */
    private const DIRECTORY = 'codex-media';

    /**
     * Store (or replace) the entry's single cover image.
     *
     * There is exactly one Cover row at all times: any existing cover — row *and*
     * file — is removed before the new one is stored. The cover is identified by its
     * collection, exposed via CodexEntry::cover(); there is no cover_media_id FK.
     */
    public function storeCover(CodexEntry $entry, UploadedFile $file): CodexMedia
    {
        $existingCover = $entry->media()
            ->where('collection', CodexMediaCollection::Cover)
            ->first();

        if ($existingCover !== null) {
            $this->remove($existingCover);
        }

        return $this->store($entry, CodexMediaCollection::Cover, $file);
    }

    /**
     * Store several uploads into one collection (reference images or files).
     *
     * Rows are created one at a time so the CodexMedia::creating() hook can assign a
     * per-(entry, collection) position to each — a bulk insert() would bypass the hook.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function storeMany(CodexEntry $entry, CodexMediaCollection $collection, array $files): void
    {
        foreach ($files as $file) {
            $this->store($entry, $collection, $file);
        }
    }

    /**
     * Delete a single media row and its file on disk.
     */
    public function remove(CodexMedia $media): void
    {
        Storage::disk(self::DISK)->delete($media->path);

        $media->delete();
    }

    /**
     * Delete every file belonging to an entry.
     *
     * Called from CodexEntry's `deleting` hook *before* the FK cascade drops the rows:
     * the cascade removes the DB rows but never the files, so without this the disk
     * would leak an orphan file per media row (see data-model.md warning). The rows
     * themselves are left to the cascade.
     */
    public function purge(CodexEntry $entry): void
    {
        $paths = $entry->media()->pluck('path')->all();

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /**
     * Persist one uploaded file: store it on disk, then record the row.
     * Position is intentionally omitted so the model hook assigns it.
     */
    private function store(CodexEntry $entry, CodexMediaCollection $collection, UploadedFile $file): CodexMedia
    {
        $path = $file->store(self::DIRECTORY, self::DISK);

        return $entry->media()->create([
            'collection' => $collection,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
