<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Guards against dev files that grow without limit (issue #287).
 */
class HousekeepingTest extends TestCase
{
    public function test_tests_do_not_write_to_the_dev_log(): void
    {
        $log = storage_path('logs/laravel.log');
        clearstatcache();
        $before = is_file($log) ? filesize($log) : 0;

        Log::error('A test must not reach the dev log.');

        clearstatcache();
        $this->assertSame($before, is_file($log) ? filesize($log) : 0);
    }

    public function test_the_purge_jobs_run_every_hour(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());

        foreach (['model:prune', 'exports:purge', 'imports:purge'] as $command) {
            $event = $events->first(fn ($event) => str_contains($event->command, $command));

            $this->assertNotNull($event, "{$command} is not scheduled.");
            $this->assertSame('0 * * * *', $event->expression, "{$command} must run hourly.");
        }
    }
}
