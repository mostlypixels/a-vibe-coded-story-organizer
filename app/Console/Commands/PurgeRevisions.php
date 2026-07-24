<?php

namespace App\Console\Commands;

use App\Services\RevisionPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The terminal entry point into App\Services\RevisionPurger — the explicit,
 * destructive counterpart to the daily `model:prune` sweep (routes/console.php).
 *
 * Owns no purge rules itself: it only parses CLI options and reports what the
 * service did. The "Revision storage" admin panel (task 13) is the other
 * caller of the same service, so the rules can never drift between the two
 * entry points.
 */
class PurgeRevisions extends Command
{
    protected $signature = 'revisions:purge
        {--project= : Only purge revisions belonging to this project ID}
        {--category= : Which category to purge (automatic, manual, labeled, imported)}
        {--before= : Only purge revisions created before this date/time (any format Carbon::parse understands)}
        {--dry-run : Report what would be removed without deleting anything}';

    protected $description = 'Explicitly delete revisions by category, project, and/or age — unlike the daily prune sweep, this can remove labeled and manual revisions';

    public function handle(RevisionPurger $purger): int
    {
        $category = $this->option('category');

        if ($category === null) {
            $this->error('The --category option is required (one of: '.implode(', ', RevisionPurger::CATEGORIES).').');

            return self::FAILURE;
        }

        $projectId = $this->option('project') !== null ? (int) $this->option('project') : null;
        $before = $this->option('before') !== null ? Carbon::parse($this->option('before')) : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $purger->purge($category, $projectId, $before, $dryRun);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $verb = $dryRun ? 'Would remove' : 'Removed';
        $sizeKilobytes = number_format($result->sizeBytes / 1024, 1);

        $this->info("{$verb} {$result->count} revision(s) ({$sizeKilobytes} KB).");

        return self::SUCCESS;
    }
}
