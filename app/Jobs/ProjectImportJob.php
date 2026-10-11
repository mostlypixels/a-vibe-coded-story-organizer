<?php

namespace App\Jobs;

use App\Exceptions\ImportAlreadyRunningException;
use App\Models\Import;
use App\Services\ProjectImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Throwable;

/**
 * Runs a project import's graph-import phases on the queue.
 *
 * Deliberately a thin wrapper: it holds NO import logic of its own — `handle()`
 * is a one-line delegation to {@see ProjectImporter::run()}, the exact same
 * entry point the controller calls inline. Whether an import runs inline or
 * queued therefore changes nothing about how it behaves; only the caller differs.
 *
 * Because the service checkpoints `phase`/`id_maps` onto the {@see Import} row
 * after each phase commits, a failed job (worker killed, exception thrown)
 * leaves the row at its last committed phase exactly as a crashed inline run
 * would — the same resume/discard actions recover both. The job needs no
 * retry/recovery logic of its own.
 *
 * Only ever dispatched when config('import.run_in_background') is on;
 * validation (ProjectImporter::start()) is always synchronous and never queued.
 *
 * The job runs once. A retry would repeat the work of a run that the writer
 * can resume from its checkpoint. The worker command keeps its own `--tries`
 * for other jobs; the properties here override it.
 *
 * > [!WARNING]
 * > The worker enforces `$timeout` only when PHP has `pcntl`. The queue
 * > `retry_after` must stay above `import.timeout_seconds`, or a second
 * > worker takes the job while the first one still runs.
 */
class ProjectImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    /** A discarded import leaves nothing to run. */
    public bool $deleteWhenMissingModels = true;

    /**
     * Seconds the worker lets one run last.
     *
     * The queue reads this property when it builds the payload. A `timeout()` method is ignored.
     */
    public int $timeout;

    public function __construct(private Import $import)
    {
        $this->timeout = (int) config('import.timeout_seconds');
    }

    public function handle(ProjectImporter $importer): void
    {
        try {
            $importer->run($this->import);
        } catch (ImportAlreadyRunningException) {
            // Another run holds the claim and finishes the import.
        }
    }

    /**
     * Records why the run stopped and releases the claim.
     *
     * A timeout kills the worker before `run()` can clear the claim or save a
     * message, so both happen here. The checkpoint stays, so the writer can resume.
     */
    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh();

        if ($import === null) {
            return;
        }

        $import->failure_message ??= $exception instanceof TimeoutExceededException
            ? __('The import took longer than :minutes minutes and stopped. You can resume it or discard it.', [
                'minutes' => intdiv($this->timeout, 60),
            ])
            : __('The import stopped. You can resume it or discard it.');
        $import->running_since = null;
        $import->save();
    }
}
