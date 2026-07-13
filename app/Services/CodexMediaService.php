<?php

namespace App\Services;

use App\Enums\CodexMediaCollection;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Project;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
     * Store the entry's single cover image (row + file), post-commit.
     *
     * The single-cover rule is settled *before* this runs: any old cover row is
     * dropped in-transaction by queueRemovals() (with `replacingCover` true) and its
     * file deleted by deleteFiles(), so by the time we write the new cover there is no
     * competing Cover row. The cover is identified by its collection, exposed via
     * CodexEntry::cover(); there is no cover_media_id FK.
     */
    public function storeCover(CodexEntry $entry, UploadedFile $file): CodexMedia
    {
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
     * Copy an already-validated file from an extracted import archive onto the
     * media disk at a freshly generated (hashed) path, returning that path.
     *
     * The archive's own relative path is never reused as the stored `path` —
     * it is meaningless outside the archive — and the naming/disk knowledge
     * stays here so the importer never learns where media lives. The caller
     * (ProjectGraphImporter) creates the row itself, because import replays
     * `position` verbatim from the archive instead of letting the creating()
     * hook derive it.
     */
    public function storeImportedFile(string $absolutePath): string
    {
        $path = Storage::disk(self::DISK)->putFile(self::DIRECTORY, new File($absolutePath));

        if ($path === false) {
            throw new \RuntimeException("Unable to copy the imported media file \"{$absolutePath}\" to storage.");
        }

        return $path;
    }

    /**
     * In-transaction: delete the media *rows* the save flow wants gone and return their
     * disk paths for deletion *after* commit.
     *
     * Two sources of removals collapse here: the explicit remove_media[] ids, and the
     * old cover row when a replacement cover is being uploaded (`$replacingCover`) — the
     * single-cover rule is thus row-level and settles inside the transaction. No disk
     * I/O happens here on purpose (finding 3): if the transaction rolls back the rows
     * are restored and the files were never touched, so no url() can 404 a surviving row.
     *
     * @param  array<int, int>  $removeIds  ids already validated to belong to $entry
     * @return array<int, string> paths to delete on disk once the transaction commits
     */
    public function queueRemovals(CodexEntry $entry, array $removeIds, bool $replacingCover): array
    {
        $paths = [];

        if ($removeIds !== []) {
            $rows = $entry->media()->whereIn('id', $removeIds)->get();
            // filter(): a metadata-only imported row has a null path — no file to delete.
            $paths = array_merge($paths, $rows->pluck('path')->filter()->values()->all());
            $entry->media()->whereIn('id', $rows->modelKeys())->delete();
        }

        if ($replacingCover) {
            $existingCover = $entry->media()
                ->where('collection', CodexMediaCollection::Cover)
                ->first();

            if ($existingCover !== null) {
                $paths[] = $existingCover->path;
                $existingCover->delete();
            }
        }

        return $paths;
    }

    /**
     * Post-commit: delete files whose rows were already dropped by queueRemovals().
     *
     * Runs after DB::transaction() returns, so a deleted file can never be resurrected
     * as a dangling row by a rollback.
     *
     * @param  array<int, string>  $paths
     */
    public function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
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
        // whereNotNull: metadata-only imported rows have no file to delete.
        $paths = $entry->media()->whereNotNull('path')->pluck('path')->all();

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /**
     * Delete every codex media file under a project before a DB cascade drops the rows.
     *
     * Called from Project's `deleting` hook (and, transitively, from User's, which
     * Eloquent-deletes its projects). The project → entries → codex_media FK cascade
     * removes the rows but never the files, so without this a project deletion would
     * leak an orphan file per media row. One query over codex_media joined through the
     * project's entries; the rows themselves are left to the cascade, mirroring purge().
     */
    public function purgeProject(Project $project): void
    {
        $paths = CodexMedia::query()
            ->whereIn('codex_entry_id', $project->codexEntries()->select('id'))
            // whereNotNull: metadata-only imported rows have no file to delete.
            ->whereNotNull('path')
            ->pluck('path')
            ->all();

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /**
     * Persist one uploaded file: store it on disk, then record the row.
     * Position is intentionally omitted so the model hook assigns it.
     *
     * Runs post-commit (see storeCover/storeMany callers), so the disk write is no
     * longer protected by a transaction rollback. If the row insert fails after the
     * file lands, unlink it before rethrowing so the failure never leaves an orphan
     * file behind (finding 3).
     */
    private function store(CodexEntry $entry, CodexMediaCollection $collection, UploadedFile $file): CodexMedia
    {
        $path = $file->store(self::DIRECTORY, self::DISK);

        try {
            return $entry->media()->create([
                'collection' => $collection,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }
}
