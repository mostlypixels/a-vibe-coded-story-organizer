<?php

namespace App\Services;

use App\Enums\ImportPhase;
use App\Exceptions\ImportAlreadyRunningException;
use App\Exceptions\ImportValidationException;
use App\Models\Import;
use App\Models\User;
use App\Services\Import\ArchiveValidator;
use App\Services\Import\ProjectGraphImporter;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Validates, stores, runs, resumes, and discards project imports.
 *
 * Validation is always synchronous and writes nothing on failure. Each completed
 * graph phase persists its checkpoint and ID maps. Working files remain after a
 * failure so the import can resume. Completion or discard removes them.
 */
class ProjectImporter
{
    /** Private disk for uploads and extracted files. */
    private const DISK = 'local';

    /** Directory for import ZIP files and extraction folders. */
    private const DIRECTORY = 'imports';

    /** Delete passes before a working folder counts as stuck. */
    private const DELETE_ATTEMPTS = 3;

    /** Suffix of the folder that holds an unpack in progress. */
    private const PARTIAL_SUFFIX = '.partial';

    /** @var array<int, ImportPhase> Phases in checkpoint order. */
    private const GRAPH_PHASES = [
        ImportPhase::Project,
        ImportPhase::Timeline,
        ImportPhase::Story,
        ImportPhase::Codex,
        ImportPhase::Notes,
        ImportPhase::Links,
    ];

    public function __construct(
        private ArchiveValidator $archiveValidator,
        private ProjectGraphImporter $graphImporter,
        private SceneReferenceMatcher $matcher,
    ) {}

    /**
     * Validates before it stores files or creates the pending import.
     * An unfinished import of the same archive refuses the upload. A completed one
     * leaves the new row at Pending: {@see Import::duplicateOf()} tells the caller not to run it.
     *
     * @throws ImportValidationException When validation fails or the archive is already importing.
     */
    public function start(UploadedFile $archive, User $user): Import
    {
        // Validate the upload before storage or database writes.
        $this->archiveValidator->validate((string) $archive->getRealPath());

        $hash = hash_file('sha256', (string) $archive->getRealPath());

        if ($this->findUnfinished($user, $hash) !== null) {
            throw ImportValidationException::alreadyImporting();
        }

        $uuid = (string) Str::uuid();
        $archivePath = $archive->storeAs(self::DIRECTORY, "{$uuid}.zip", self::DISK);

        if ($archivePath === false) {
            throw new RuntimeException('Unable to store the uploaded import archive.');
        }

        // run() extracts the ZIP, so the long unpack happens in the job, not the request.
        return $user->imports()->create([
            'archive_path' => $archivePath,
            'archive_original_name' => $archive->getClientOriginalName(),
            'archive_sha256' => $hash,
            'phase' => ImportPhase::Pending,
        ]);
    }

    /** Finds the user's queued, running, or stalled import of the same archive bytes. */
    private function findUnfinished(User $user, string $hash): ?Import
    {
        return $user->imports()
            ->where('archive_sha256', $hash)
            ->where('phase', '!=', ImportPhase::Completed)
            ->first();
    }

    /**
     * Runs remaining phases and saves a safe failure message without losing the checkpoint.
     *
     * @throws ImportAlreadyRunningException When another run holds a fresh claim.
     */
    public function run(Import $import): void
    {
        $claimedAt = $this->claim($import);

        try {
            $this->runPhases($import);
        } finally {
            $this->release($import, $claimedAt);
        }
    }

    /**
     * Takes the run claim with one conditional UPDATE, so two runs cannot both win.
     * A claim older than `import.timeout_seconds` is a crashed run and is taken over.
     * The claim is a column, not a cache lock, because the import page reads it.
     *
     * @throws ImportAlreadyRunningException
     */
    private function claim(Import $import): CarbonInterface
    {
        $now = now();

        $claimed = Import::query()
            ->whereKey($import->getKey())
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('running_since')
                    ->orWhere('running_since', '<', $now->copy()->subSeconds(config('import.timeout_seconds')));
            })
            ->update(['running_since' => $now, 'queued_at' => null]);

        if ($claimed === 0) {
            throw new ImportAlreadyRunningException;
        }

        // Reload the checkpoint: a crashed run can have saved phases after this model was read.
        $import->refresh();

        return $now;
    }

    /** Clears the claim only if it is still this run's: a takeover owns it after a timeout. */
    private function release(Import $import, CarbonInterface $claimedAt): void
    {
        Import::query()
            ->whereKey($import->getKey())
            ->where('running_since', $claimedAt)
            ->update(['running_since' => null]);

        $import->forceFill(['running_since' => null])->syncOriginalAttribute('running_since');
    }

    private function runPhases(Import $import): void
    {
        if (in_array($import->phase, [ImportPhase::Completed, ImportPhase::Failed], true)) {
            return; // terminal — nothing to run
        }

        $dataPath = $this->extractionPath($import);

        // Extract the kept ZIP on the first run, or again when the extracted files are gone.
        if (! is_dir($dataPath)) {
            try {
                $this->extract($import);
            } catch (Throwable $exception) {
                $import->update(['failure_message' => $exception instanceof ImportValidationException
                    ? $exception->getMessage()
                    : 'The import could not unpack the archive. You can resume it or discard it.']);

                throw $exception;
            }
        }

        $idMaps = $import->id_maps ?? [];

        foreach ($this->remainingPhases($import->phase) as $phase) {
            try {
                $this->runPhase($phase, $dataPath, $import, $idMaps);
            } catch (Throwable $exception) {
                $import->update(['failure_message' => $this->safeFailureMessage($phase, $exception)]);

                throw $exception;
            }

            // Persist the checkpoint immediately after the phase commits.
            $import->fill([
                'phase' => $phase,
                'id_maps' => $idMaps,
                'failure_message' => null,
            ])->save();
        }

        // Rebuild the derived scene-reference cache after story and codex exist.
        $this->matcher->syncProject($import->project);

        DB::transaction(function () use ($import): void {
            $import->project->update(['import_unfinished' => false]);
            $import->update(['phase' => ImportPhase::Completed]);
        });

        $this->deleteWorkingFiles($import);
    }

    /** Deletes a partial project, working files, and its import row. */
    public function discard(Import $import): void
    {
        $import->project?->delete();

        $this->deleteWorkingFiles($import);

        $import->delete();
    }

    /**
     * Deletes the working files and row of each unfinished import unchanged since $before.
     * Keeps a partial project because the writer can have edited it.
     */
    public function purgeStale(CarbonInterface $before): int
    {
        $staleImports = Import::query()
            ->where('phase', '!=', ImportPhase::Completed)
            ->where('updated_at', '<', $before)
            ->get();

        foreach ($staleImports as $import) {
            $this->deleteWorkingFiles($import);
            $import->delete();
        }

        return $staleImports->count();
    }

    /**
     * Deletes working files older than $before that no unfinished import owns.
     * Account deletion drops import rows at the DB level, so no model hook removes them.
     */
    public function purgeOrphanedFiles(CarbonInterface $before): int
    {
        $ownedDirectories = Import::query()
            ->where('phase', '!=', ImportPhase::Completed)
            ->pluck('archive_path')
            ->map(fn (string $archivePath) => Str::beforeLast($archivePath, '.zip'))
            ->all();

        $entries = [
            ...$this->disk()->files(self::DIRECTORY),
            ...$this->disk()->directories(self::DIRECTORY),
        ];
        $count = 0;

        foreach ($entries as $path) {
            // The age check protects an upload that has files but no row yet.
            if (in_array(Str::beforeLast($path, '.zip'), $ownedDirectories, true)
                || filemtime($this->disk()->path($path)) >= $before->getTimestamp()) {
                continue;
            }

            is_dir($this->disk()->path($path))
                ? $this->deleteDirectory($path)
                : $this->disk()->delete($path);

            $count++;
        }

        return $count;
    }

    /** @param array<string, array<int, int>> $idMaps */
    private function runPhase(ImportPhase $phase, string $dataPath, Import $import, array &$idMaps): void
    {
        match ($phase) {
            ImportPhase::Project => $import->project()->associate(
                $this->graphImporter->importProject($dataPath, $import->user),
            ),
            ImportPhase::Timeline => $this->graphImporter->importTimeline($dataPath, $import->project, $idMaps),
            ImportPhase::Story => $this->graphImporter->importStory($dataPath, $import->project, $idMaps),
            ImportPhase::Codex => $this->graphImporter->importCodex($dataPath, $import->project, $idMaps),
            ImportPhase::Notes => $this->graphImporter->importNotes($dataPath, $import->project, $idMaps),
            ImportPhase::Links => $this->graphImporter->importLinks($import->project, $idMaps),
            default => throw new RuntimeException("\"{$phase->value}\" is not a runnable import phase."),
        };
    }

    /** @return array<int, ImportPhase> */
    private function remainingPhases(ImportPhase $completed): array
    {
        $index = array_search($completed, self::GRAPH_PHASES, true);

        return $index === false
            ? self::GRAPH_PHASES
            : array_slice(self::GRAPH_PHASES, $index + 1);
    }

    /** Hides internal exception details but preserves safe validation messages. */
    private function safeFailureMessage(ImportPhase $phase, Throwable $exception): string
    {
        return $exception instanceof ImportValidationException
            ? $exception->getMessage()
            : "The import failed while importing the {$phase->value} data. You can resume it or discard it.";
    }

    /**
     * Extracts the validated archive into a side folder, then renames the folder.
     *
     * > [!WARNING]
     * > A job timeout can stop the unpack. A partial folder at the final path looks complete
     * > to the next run. The side folder makes the next run extract again.
     */
    private function extract(Import $import): void
    {
        $partialDirectory = $this->extractedDirectory($import).self::PARTIAL_SUFFIX;
        $this->deleteDirectory($partialDirectory);

        $zip = new ZipArchive;

        if ($zip->open($this->disk()->path($import->archive_path)) !== true) {
            throw ImportValidationException::notAZip();
        }

        try {
            if (! $zip->extractTo($this->disk()->path($partialDirectory))) {
                throw new RuntimeException('Unable to extract the import archive.');
            }
        } finally {
            $zip->close();
        }

        if (! rename($this->disk()->path($partialDirectory), $this->extractionPath($import))) {
            throw new RuntimeException('Unable to move the extracted import archive.');
        }
    }

    /** Deletes working files after completion or discard. */
    private function deleteWorkingFiles(Import $import): void
    {
        $this->disk()->delete($import->archive_path);
        $this->deleteDirectory($this->extractedDirectory($import));
        $this->deleteDirectory($this->extractedDirectory($import).self::PARTIAL_SUFFIX);
    }

    /**
     * Deletes a folder, with more passes when one pass leaves files.
     *
     * On a Docker bind mount under Windows, one pass can skip files and return false.
     * The extracted files hold the writer's text, so a failure gets a log warning.
     */
    private function deleteDirectory(string $path): void
    {
        for ($attempt = 0; $attempt < self::DELETE_ATTEMPTS && $this->disk()->directoryExists($path); $attempt++) {
            $this->disk()->deleteDirectory($path);
        }

        if ($this->disk()->directoryExists($path)) {
            Log::warning('Import working files could not be deleted.', ['path' => $path]);
        }
    }

    /** Returns the absolute extraction directory. */
    private function extractionPath(Import $import): string
    {
        return $this->disk()->path($this->extractedDirectory($import));
    }

    /** Returns the extraction directory relative to the disk root. */
    private function extractedDirectory(Import $import): string
    {
        return Str::beforeLast($import->archive_path, '.zip');
    }

    /** Returns the private import disk. */
    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
