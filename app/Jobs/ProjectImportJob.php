<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ProjectImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
 * Only ever dispatched when ImportSetting::current()->run_in_background is on;
 * validation (ProjectImporter::start()) is always synchronous and never queued.
 */
class ProjectImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private Import $import) {}

    public function handle(ProjectImporter $importer): void
    {
        $importer->run($this->import);
    }
}
