<?php

namespace App\Console\Commands;

use App\Services\ProjectImporter;
use Illuminate\Console\Command;

/**
 * Removes import working files that no resume or discard will remove.
 *
 * An import keeps its ZIP and extracted folder until it completes or the writer
 * discards it. An abandoned import, or one whose owner deleted the account, keeps
 * them forever. The daily schedule in routes/console.php runs this sweep.
 *
 * > [!WARNING]
 * > The age window (`--days`) keeps this command away from an import in progress.
 * > Do not set it to 0 on a live site.
 */
class PurgeImports extends Command
{
    protected $signature = 'imports:purge
        {--days= : Remove unfinished imports older than this many days (default: import.purge_after_days)}';

    protected $description = 'Delete unfinished imports and leftover import files older than the retention window';

    public function handle(ProjectImporter $importer): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('import.purge_after_days');

        if ($days < 0) {
            $this->error('The --days option cannot be negative.');

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $imports = $importer->purgeStale($before);
        $orphans = $importer->purgeOrphanedFiles($before);

        $this->info("Removed {$imports} stale import(s) and {$orphans} orphaned file(s) or folder(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
