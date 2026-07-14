<?php

namespace App\Services;

use App\Enums\ImportPhase;
use App\Exceptions\ImportValidationException;
use App\Models\Import;
use App\Models\User;
use App\Services\Import\ArchiveValidator;
use App\Services\Import\ProjectGraphImporter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * The import orchestrator: ties the security gate ({@see ArchiveValidator}),
 * the graph importer ({@see ProjectGraphImporter}), and the {@see Import}
 * checkpoint record together behind three HTTP-agnostic entry points —
 * `start()`, `run()`, `discard()` — callable identically from the controller
 * (inline, task 06) or the queued ProjectImportJob (task 07).
 *
 * The checkpoint contract (see .specs → import → data-model.md):
 *   - `start()` validates the WHOLE archive synchronously (always — even when
 *     background mode is on, so a validation failure surfaces immediately as a
 *     form error). Only on success does it store the zip, extract it, and
 *     create the Import row at phase = pending. A failed validation creates
 *     no row and leaves no files behind.
 *   - `run()` executes the four graph phases in order, starting AFTER the
 *     row's recorded phase, persisting `phase` + the accumulated `id_maps`
 *     immediately after each phase's own transaction commits. A crash or
 *     exception therefore leaves the row at its last committed phase — always
 *     resumable (call run() again) or discardable, never a silent orphan.
 *   - The uploaded zip and its extracted directory deliberately SURVIVE a
 *     crash (no finally-cleanup like the exporter's temp file) — they are the
 *     material a resume runs from, deleted only on `completed` or `discard()`.
 */
class ProjectImporter
{
    /**
     * The private disk holding uploaded archives and their extractions —
     * never the public disk; an archive must not be web-servable.
     */
    private const DISK = 'local';

    /**
     * The directory (relative to the disk root) all import working files
     * live under: `imports/<uuid>.zip` next to its extraction `imports/<uuid>/`.
     */
    private const DIRECTORY = 'imports';

    /**
     * The graph-import phases in execution order. `run()` resumes at the one
     * AFTER the Import row's recorded phase (Pending precedes them all).
     *
     * @var array<int, ImportPhase>
     */
    private const GRAPH_PHASES = [
        ImportPhase::Project,
        ImportPhase::Timeline,
        ImportPhase::Story,
        ImportPhase::Codex,
    ];

    public function __construct(
        private ArchiveValidator $archiveValidator,
        private ProjectGraphImporter $graphImporter,
        private SceneReferenceMatcher $matcher,
    ) {}

    /**
     * Validate an uploaded archive and, only if it passes, persist it and
     * create the tracking row (phase = pending). Always synchronous — the
     * run_in_background toggle only ever defers `run()`, never validation.
     *
     * @throws ImportValidationException when the archive fails validation —
     *                                   in that case NO Import row is created
     *                                   and nothing is written to storage.
     */
    public function start(UploadedFile $archive, User $user): Import
    {
        // The security gate runs against the upload's temp file BEFORE the
        // archive is copied anywhere or any row is written (core invariant).
        $this->archiveValidator->validate((string) $archive->getRealPath());

        $uuid = (string) Str::uuid();
        $archivePath = $archive->storeAs(self::DIRECTORY, "{$uuid}.zip", self::DISK);

        if ($archivePath === false) {
            throw new RuntimeException('Unable to store the uploaded import archive.');
        }

        try {
            $this->extract(
                $this->disk()->path($archivePath),
                $this->disk()->path(self::DIRECTORY."/{$uuid}"),
            );
        } catch (Throwable $exception) {
            // No Import row exists yet, so a failed extraction must not leave
            // an untracked zip behind either.
            $this->disk()->delete($archivePath);

            throw $exception;
        }

        // user_id is deliberately not mass-assignable on Import (same rule as
        // Project) — creating through the owner relation sets it.
        return $user->imports()->create([
            'archive_path' => $archivePath,
            'archive_original_name' => $archive->getClientOriginalName(),
            'phase' => ImportPhase::Pending,
        ]);
    }

    /**
     * Execute the graph-import phases from wherever this Import last stopped,
     * checkpointing `phase` + `id_maps` onto the row after each phase's
     * transaction commits. On the final (codex) phase's success the row is
     * marked completed and the working files (zip + extraction) are deleted.
     *
     * On any phase failure the exception is RETHROWN — never swallowed; the
     * caller (controller or queued job) decides how to surface it — after
     * `failure_message` is set to a safe, user-displayable string. The row
     * stays at its last committed phase, ready to resume or discard.
     */
    public function run(Import $import): void
    {
        if (in_array($import->phase, [ImportPhase::Completed, ImportPhase::Failed], true)) {
            return; // terminal — nothing to run
        }

        $dataPath = $this->extractionPath($import);

        // Resume support: the extraction normally survives from start(), but a
        // deploy/reboot may have cleared it — the kept zip is the source of
        // truth to re-extract from (data-model.md → Resume).
        if (! is_dir($dataPath)) {
            $this->extract($this->disk()->path($import->archive_path), $dataPath);
        }

        // The id maps accumulated by already-committed phases; phases run here
        // append to them, and each checkpoint persists the whole set.
        $idMaps = $import->id_maps ?? [];

        foreach ($this->remainingPhases($import->phase) as $phase) {
            try {
                $this->runPhase($phase, $dataPath, $import, $idMaps);
            } catch (Throwable $exception) {
                $import->update(['failure_message' => $this->safeFailureMessage($phase, $exception)]);

                throw $exception;
            }

            // Checkpoint IMMEDIATELY after the phase's transaction committed,
            // so a crash between phases loses nothing. project_id (associated
            // by the project phase) rides along on the same save.
            $import->fill([
                'phase' => $phase,
                'id_maps' => $idMaps,
                'failure_message' => null,
            ])->save();
        }

        // Regenerate the derived scene_codex_entry cache once, now that BOTH
        // halves the matcher needs exist: scenes (with their final contents,
        // from the Story phase) and codex entries/aliases (from the Codex
        // phase). The archive never carries this pivot — it is recomputed here
        // so an imported project ends up with exactly the references a native
        // save would have produced (architecture.md → Import/export interaction).
        //
        // This is deliberately NOT a fifth ImportPhase: it accumulates no
        // id_map and nothing in the archive maps onto it. It sits after the
        // remainingPhases() loop and before marking Completed — a point reached
        // exactly once per finishing import, on whichever run() call gets there,
        // including a resume whose loop is empty. syncProject() is a full
        // resync (idempotent), so a crash between here and Completed simply
        // retries it safely on the next run().
        $this->matcher->syncProject($import->project);

        $import->update(['phase' => ImportPhase::Completed]);

        // Nothing left to resume from — drop the working files, exactly like
        // the exporter deletes its temp zip once sent.
        $this->deleteWorkingFiles($import);
    }

    /**
     * Roll back a stalled import: delete the partially-built Project (its own
     * cascade/deleting hooks purge children and media files), the working
     * files on disk, and the Import row itself. An explicit user action —
     * never triggered automatically.
     */
    public function discard(Import $import): void
    {
        $import->project?->delete();

        $this->deleteWorkingFiles($import);

        $import->delete();
    }

    /**
     * Dispatch one graph phase to its ProjectGraphImporter method. The
     * project phase additionally associates the fresh Project with the Import
     * row (persisted by the checkpoint save that follows).
     *
     * @param  array<string, array<int, int>>  $idMaps
     */
    private function runPhase(ImportPhase $phase, string $dataPath, Import $import, array &$idMaps): void
    {
        match ($phase) {
            ImportPhase::Project => $import->project()->associate(
                $this->graphImporter->importProject($dataPath, $import->user),
            ),
            ImportPhase::Timeline => $this->graphImporter->importTimeline($dataPath, $import->project, $idMaps),
            ImportPhase::Story => $this->graphImporter->importStory($dataPath, $import->project, $idMaps),
            ImportPhase::Codex => $this->graphImporter->importCodex($dataPath, $import->project, $idMaps),
            default => throw new RuntimeException("\"{$phase->value}\" is not a runnable import phase."),
        };
    }

    /**
     * The graph phases still to run after the given (last completed) phase.
     * Pending precedes them all; the phase after codex is none.
     *
     * @return array<int, ImportPhase>
     */
    private function remainingPhases(ImportPhase $completed): array
    {
        $index = array_search($completed, self::GRAPH_PHASES, true);

        return $index === false
            ? self::GRAPH_PHASES
            : array_slice(self::GRAPH_PHASES, $index + 1);
    }

    /**
     * A user-displayable failure message for the Import row. A validation
     * exception's message is user-safe by construction (it names archive
     * entries from the user's own upload); anything else gets a generic
     * phase-naming string — never a raw exception message, which could leak
     * an internal path or SQL fragment.
     */
    private function safeFailureMessage(ImportPhase $phase, Throwable $exception): string
    {
        return $exception instanceof ImportValidationException
            ? $exception->getMessage()
            : "The import failed while importing the {$phase->value} data. You can resume it or discard it.";
    }

    /**
     * Extract a (already validated — every entry name passed the zip-slip and
     * allow-list checks) archive to the given directory.
     */
    private function extract(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw ImportValidationException::notAZip();
        }

        try {
            if (! $zip->extractTo($destination)) {
                throw new RuntimeException('Unable to extract the import archive.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Delete the uploaded zip and its extracted directory. Only ever called
     * on completion or discard — a stalled import keeps both for resume.
     */
    private function deleteWorkingFiles(Import $import): void
    {
        $this->disk()->delete($import->archive_path);
        $this->disk()->deleteDirectory($this->extractedDirectory($import));
    }

    /**
     * The extraction directory's absolute path — what ProjectGraphImporter
     * receives as its `$dataPath` (the directory containing `data/`).
     */
    private function extractionPath(Import $import): string
    {
        return $this->disk()->path($this->extractedDirectory($import));
    }

    /**
     * The extraction directory relative to the disk root: the archive's own
     * path minus its .zip suffix (`imports/<uuid>.zip` → `imports/<uuid>`).
     */
    private function extractedDirectory(Import $import): string
    {
        return Str::beforeLast($import->archive_path, '.zip');
    }

    /**
     * The private disk every import working file lives on.
     */
    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
