<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes a snapshot of the SQLite database and deletes the older snapshots of the same name.
 *
 * The snapshot uses `VACUUM INTO`. It gives a consistent copy while the app runs, and it never
 * writes to the live file. A file copy of a database in use can give a torn file.
 *
 * Retention counts per name. Scheduled snapshots never delete a manual `before-migrate`
 * snapshot. The schedule in routes/console.php runs this command. To restore, see
 * documentation/development/database-backups.md.
 *
 * > [!WARNING]
 * > SQLite refuses `VACUUM` inside a transaction. Do not call this command from inside
 * > `DB::transaction()`, or from a test that uses `RefreshDatabase`.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--name= : Name for this snapshot, letters, digits, - and _ only (default: backup.default_name)}
        {--keep= : How many snapshots of this name to keep (default: backup.keep)}
        {--path= : Directory to write into (default: backup.path)}
        {--dry-run : Report what would happen without writing or deleting anything}';

    protected $description = 'Write a snapshot of the SQLite database and delete older snapshots of the same name';

    private const TIMESTAMP_FORMAT = 'Y-m-d_His';

    private const TIMESTAMP_PATTERN = '\d{4}-\d{2}-\d{2}_\d{6}';

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            $this->error("The database connection [{$connection->getName()}] is not SQLite. db:backup only supports SQLite.");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? config('backup.default_name'));

        // The name is part of a file name. Reject a bad name: a quietly changed name is hard to find later.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            $this->error("The name [{$name}] is not valid. Use only letters, digits, - and _.");

            return self::FAILURE;
        }

        $keep = (int) ($this->option('keep') ?? config('backup.keep'));

        if ($keep < 1) {
            $this->error('The --keep option must be at least 1.');

            return self::FAILURE;
        }

        $directory = rtrim((string) ($this->option('path') ?? config('backup.path')), '/\\');
        $dryRun = (bool) $this->option('dry-run');
        $target = $directory.DIRECTORY_SEPARATOR.$name.'-'.now()->format(self::TIMESTAMP_FORMAT).'.sqlite';

        // A second run in the same second has nothing new to save. Refuse, so the double run is visible.
        if (file_exists($target)) {
            $this->error("A snapshot already exists at {$target}. Wait one second and try again.");

            return self::FAILURE;
        }

        // The new snapshot counts as one of the kept snapshots.
        $obsolete = array_slice($this->snapshotsOf($directory, $name), $keep - 1);

        if ($dryRun) {
            $this->info("Would write {$target}.");
            $this->info('Would remove '.count($obsolete)." older [{$name}] snapshot(s), keeping {$keep}.");

            return self::SUCCESS;
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create the backup directory {$directory}.");

            return self::FAILURE;
        }

        if (! is_writable($directory)) {
            $this->error("The backup directory {$directory} is not writable.");

            return self::FAILURE;
        }

        // Write before the prune, so a failed snapshot never costs an old one.
        DB::statement('VACUUM INTO ?', [$target]);

        $removed = 0;

        foreach ($obsolete as $file) {
            if (! @unlink($file)) {
                $this->error("Could not delete {$file}.");

                return self::FAILURE;
            }

            $removed++;
        }

        $this->info("Wrote {$target}.");
        $this->info("Removed {$removed} older [{$name}] snapshot(s), keeping {$keep}.");

        return self::SUCCESS;
    }

    /**
     * The cron expression for a snapshot every `$hours` hours, at minute 0.
     *
     * Laravel has no "every N hours" helper for an arbitrary N, so the schedule uses cron.
     */
    public static function cronEvery(int $hours): string
    {
        if ($hours < 1 || $hours > 24) {
            throw new InvalidArgumentException("backup.every_hours must be from 1 to 24, [{$hours}] given.");
        }

        return $hours === 24 ? '0 0 * * *' : "0 */{$hours} * * *";
    }

    /**
     * The snapshots of one name in the directory, newest first.
     *
     * The exact pattern keeps other names out: a glob for `before-*` also matches `before-migrate-*`.
     * The timestamp sorts in time order, so a sort by file name is enough.
     *
     * @return list<string>
     */
    private function snapshotsOf(string $directory, string $name): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $pattern = '/^'.preg_quote($name, '/').'-'.self::TIMESTAMP_PATTERN.'\.sqlite$/';
        $files = [];

        foreach ((array) scandir($directory) as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;

            if (preg_match($pattern, (string) $file) === 1 && is_file($path)) {
                $files[] = $path;
            }
        }

        rsort($files);

        return $files;
    }
}
