<?php

namespace App\Console\Commands;

use Database\Seeders\SecondUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * A superset of `app:install-demo`: the demo plus a second, non-owner user
 * (`writer@example.com`) for 403 checks and visual review. `make seed` and
 * `make fresh` run this command so they need only one seed step besides
 * `db:seed`.
 */
class InstallTestFixturesCommand extends Command
{
    protected $signature = 'app:install-test-fixtures
        {--user= : Email or ID of the demo owner; defaults to the first user}';

    protected $description = 'Install the demo and a second user for authorization checks';

    public function handle(): int
    {
        $options = [];

        if ($this->option('user') !== null) {
            $options['--user'] = $this->option('user');
        }

        $demoExitCode = $this->call('app:install-demo', $options);

        if ($demoExitCode !== self::SUCCESS) {
            return $demoExitCode;
        }

        // SecondUserSeeder sets user_id, which is not fillable, and builds its
        // book by hand, so both unguarding and events-off are required here.
        Model::unguarded(fn () => Model::withoutEvents(function (): void {
            app(SecondUserSeeder::class)->run();
        }));

        $this->info('Installed the second user for authorization checks.');

        return self::SUCCESS;
    }
}
