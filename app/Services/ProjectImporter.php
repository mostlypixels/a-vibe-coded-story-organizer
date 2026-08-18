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

    /** @var array<int, ImportPhase> Phases in checkpoint order. */
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
     * Validates before it stores files or creates the pending import.
     *
     * @throws ImportValidationException When validation fails.
     */
    public function start(UploadedFile $archive, User $user): Import
    {
        // Validate the upload before storage or database writes.
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
            // Remove files when extraction fails before the tracking row exists.
            $this->disk()->delete($archivePath);

            throw $exception;
        }

        return $user->imports()->create([
            'archive_path' => $archivePath,
            'archive_original_name' => $archive->getClientOriginalName(),
            'phase' => ImportPhase::Pending,
        ]);
    }

    /** Runs remaining phases and saves a safe failure message without losing the checkpoint. */
    public function run(Import $import): void
    {
        if (in_array($import->phase, [ImportPhase::Completed, ImportPhase::Failed], true)) {
            return; // terminal — nothing to run
        }

        $dataPath = $this->extractionPath($import);

        // Re-extract the kept ZIP when temporary extracted files are missing.
        if (! is_dir($dataPath)) {
            $this->extract($this->disk()->path($import->archive_path), $dataPath);
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

        $import->update(['phase' => ImportPhase::Completed]);

        $this->deleteWorkingFiles($import);
    }

    /** Deletes a partial project, working files, and its import row. */
    public function discard(Import $import): void
    {
        $import->project?->delete();

        $this->deleteWorkingFiles($import);

        $import->delete();
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

    /** Extracts an archive after all entry names pass validation. */
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

    /** Deletes working files after completion or discard. */
    private function deleteWorkingFiles(Import $import): void
    {
        $this->disk()->delete($import->archive_path);
        $this->disk()->deleteDirectory($this->extractedDirectory($import));
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
