<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Removes the leftover files in the `exports.temp_path` directory.
 *
 * A generated .zip/.epub is meant to live for one request: the controller streams
 * it with BinaryFileResponse::deleteFileAfterSend(). The file survives when the
 * response never streams — an aborted download, or a test that asserts on the
 * response instead of sending it. Nothing fails, so the directory grows in
 * silence. The daily schedule in routes/console.php runs this sweep.
 *
 * > [!WARNING]
 * > An export in progress is a file in that same directory. The age window
 * > (`--hours`) is what keeps this command from deleting a download that another
 * > request is still writing or streaming. Do not set it to 0 on a live site.
 */
class PurgeExports extends Command
{
    protected $signature = 'exports:purge
        {--hours= : Remove files older than this many hours (default: exports.purge_after_hours)}
        {--dry-run : Report what would be removed without deleting anything}';

    protected $description = 'Delete leftover temporary export files that were generated but never streamed to a client';

    public function handle(): int
    {
        $hours = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : (int) config('exports.purge_after_hours');

        if ($hours < 0) {
            $this->error('The --hours option cannot be negative.');

            return self::FAILURE;
        }

        $directory = config('exports.temp_path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_dir($directory)) {
            $this->info("No export directory at {$directory} — nothing to purge.");

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $count = 0;
        $sizeBytes = 0;

        foreach ((array) glob($directory.DIRECTORY_SEPARATOR.'*') as $file) {
            // Only flat files are exports. A directory here belongs to something
            // else, and deleting it is not this command's business.
            if (! is_file($file) || filemtime($file) > $cutoff) {
                continue;
            }

            $size = filesize($file);

            if (! $dryRun && ! @unlink($file)) {
                $this->warn("Could not delete {$file} — skipped.");

                continue;
            }

            $count++;
            $sizeBytes += $size;
        }

        $verb = $dryRun ? 'Would remove' : 'Removed';
        $sizeKilobytes = number_format($sizeBytes / 1024, 1);

        $this->info("{$verb} {$count} export file(s) ({$sizeKilobytes} KB) older than {$hours}h.");

        return self::SUCCESS;
    }
}
