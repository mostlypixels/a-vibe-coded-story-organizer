<?php

namespace App\Console\Commands;

use App\Models\Project;
use Database\Seeders\Concerns\SeedsWordCountHistory;
use Illuminate\Console\Command;

/**
 * A thin, ad-hoc wrapper around `Database\Seeders\Concerns\SeedsWordCountHistory`
 * — the same generator the Melusine seeders use, for eyeballing the Progress
 * chart on any one project without a full `db:seed`. Precedent: `theme:ramp`
 * (ThemeRampCommand), an authoring tool with no runtime role.
 */
class WordCountDemoHistoryCommand extends Command
{
    use SeedsWordCountHistory;

    protected $signature = 'word-count:demo-history
        {project : The project ID to generate history for}
        {--days=60 : How many days of history to generate}
        {--seed= : Override the default seed (a hash of the project name)}';

    protected $description = 'Generate a plausible writing history for one project, ending on its real word count';

    public function handle(): int
    {
        $project = Project::find((int) $this->argument('project'));

        if ($project === null) {
            $this->error("No project with ID {$this->argument('project')}.");

            return self::FAILURE;
        }

        $days = (int) $this->option('days');

        if ($days < 3) {
            $this->error('--days must be at least 3.');

            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : null;

        $this->seedWordCountHistory($project, $days, $seed);

        $this->info("Generated {$days} day(s) of history for \"{$project->name}\".");

        return self::SUCCESS;
    }
}
