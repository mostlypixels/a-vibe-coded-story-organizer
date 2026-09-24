<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use Tests\TestCase;

/**
 * Covers `db:backup`, the rotating SQLite snapshot.
 *
 * This class does not use `RefreshDatabase`. That trait wraps each test in a transaction,
 * and SQLite refuses `VACUUM INTO` inside a transaction. Each test points the `sqlite`
 * connection at its own temporary database file instead.
 */
class BackupDatabaseTest extends TestCase
{
    private string $workDirectory;

    private string $backupDirectory;

    private string $sourceDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = storage_path('framework/testing/backups/'.Str::random(16));
        $this->backupDirectory = $this->workDirectory.DIRECTORY_SEPARATOR.'snapshots';
        $this->sourceDatabase = $this->workDirectory.DIRECTORY_SEPARATOR.'source.sqlite';

        mkdir($this->workDirectory, 0755, true);
        touch($this->sourceDatabase);

        config([
            'database.connections.sqlite.database' => $this->sourceDatabase,
            'backup.path' => $this->backupDirectory,
            'backup.keep' => 48,
            'backup.default_name' => 'scheduled',
        ]);
        DB::purge('sqlite');

        DB::statement('CREATE TABLE notes (body TEXT)');
        DB::table('notes')->insert(['body' => 'Melusine keeps her secret']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        $this->removeDirectory($this->workDirectory);

        parent::tearDown();
    }

    public function test_it_writes_a_valid_sqlite_snapshot_with_the_data(): void
    {
        $this->freezeTime(function () {
            $this->artisan('db:backup')->assertSuccessful();

            $snapshot = $this->backupDirectory.DIRECTORY_SEPARATOR.'scheduled-'.now()->format('Y-m-d_His').'.sqlite';
            $this->assertFileExists($snapshot);

            $pdo = new PDO('sqlite:'.$snapshot);
            $body = $pdo->query('SELECT body FROM notes')->fetchColumn();
            $pdo = null;

            $this->assertSame('Melusine keeps her secret', $body);
        });
    }

    public function test_the_name_option_appears_in_the_file_name(): void
    {
        $this->artisan('db:backup', ['--name' => 'before-migrate'])->assertSuccessful();

        $this->assertCount(1, glob($this->backupDirectory.'/before-migrate-*.sqlite'));
    }

    public function test_a_name_with_a_slash_or_a_space_is_rejected(): void
    {
        $this->artisan('db:backup', ['--name' => '../escape'])->assertFailed();
        $this->artisan('db:backup', ['--name' => 'two words'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->backupDirectory);
    }

    public function test_retention_keeps_the_newest_of_the_same_name_only(): void
    {
        $old = [
            $this->makeSnapshot('scheduled-2026-01-01_000000.sqlite'),
            $this->makeSnapshot('scheduled-2026-01-02_000000.sqlite'),
            $this->makeSnapshot('scheduled-2026-01-03_000000.sqlite'),
        ];
        // A name that starts with the scheduled name must not count as a scheduled snapshot.
        $otherName = $this->makeSnapshot('scheduled-extra-2026-01-01_000000.sqlite');
        $manual = $this->makeSnapshot('before-migrate-2026-01-01_000000.sqlite');
        $foreign = $this->makeSnapshot('notes.txt');

        config(['backup.keep' => 2]);

        $this->artisan('db:backup')
            ->expectsOutputToContain('Removed 2 older [scheduled] snapshot(s), keeping 2.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($old[0]);
        $this->assertFileDoesNotExist($old[1]);
        $this->assertFileExists($old[2]);
        $this->assertFileExists($otherName);
        $this->assertFileExists($manual);
        $this->assertFileExists($foreign);
    }

    public function test_the_keep_option_overrides_the_config(): void
    {
        $older = $this->makeSnapshot('scheduled-2026-01-01_000000.sqlite');
        $newer = $this->makeSnapshot('scheduled-2026-01-02_000000.sqlite');

        $this->artisan('db:backup', ['--keep' => 1])->assertSuccessful();

        $this->assertFileDoesNotExist($older);
        $this->assertFileDoesNotExist($newer);
        $this->assertCount(1, glob($this->backupDirectory.'/scheduled-*.sqlite'));
    }

    public function test_a_keep_below_one_is_rejected(): void
    {
        $this->artisan('db:backup', ['--keep' => 0])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->backupDirectory);
    }

    public function test_the_path_option_overrides_the_config_and_creates_the_directory(): void
    {
        $elsewhere = $this->workDirectory.DIRECTORY_SEPARATOR.'elsewhere'.DIRECTORY_SEPARATOR.'deep';

        $this->artisan('db:backup', ['--path' => $elsewhere])->assertSuccessful();

        $this->assertCount(1, glob($elsewhere.'/scheduled-*.sqlite'));
        $this->assertDirectoryDoesNotExist($this->backupDirectory);
    }

    public function test_a_dry_run_writes_and_deletes_nothing(): void
    {
        config(['backup.keep' => 1]);
        $old = $this->makeSnapshot('scheduled-2026-01-01_000000.sqlite');

        $this->artisan('db:backup', ['--dry-run' => true])
            ->expectsOutputToContain('Would write')
            ->expectsOutputToContain('Would remove 1 older [scheduled] snapshot(s)')
            ->assertSuccessful();

        $this->assertFileExists($old);
        $this->assertCount(1, glob($this->backupDirectory.'/*'));
    }

    public function test_a_second_run_in_the_same_second_is_refused(): void
    {
        $this->freezeTime(function () {
            $this->artisan('db:backup')->assertSuccessful();

            $snapshot = glob($this->backupDirectory.'/scheduled-*.sqlite')[0];
            $firstRun = file_get_contents($snapshot);

            $this->artisan('db:backup')
                ->expectsOutputToContain('already exists')
                ->assertFailed();

            $this->assertSame($firstRun, file_get_contents($snapshot));
        });
    }

    public function test_a_non_sqlite_connection_aborts(): void
    {
        config(['database.default' => 'mysql']);

        $this->artisan('db:backup')
            ->expectsOutputToContain('[mysql] is not SQLite')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->backupDirectory);
    }

    public function test_the_schedule_runs_the_backup_at_the_configured_interval(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'db:backup'));

        $this->assertNotNull($event);
        $this->assertSame('0 */1 * * *', $event->expression);
    }

    public function test_the_interval_builds_a_cron_expression_from_one_to_24_hours(): void
    {
        $this->assertSame('0 */6 * * *', BackupDatabase::cronEvery(6));
        $this->assertSame('0 0 * * *', BackupDatabase::cronEvery(24));

        $this->expectException(InvalidArgumentException::class);
        BackupDatabase::cronEvery(25);
    }

    private function makeSnapshot(string $name): string
    {
        if (! is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0755, true);
        }

        $path = $this->backupDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, 'old snapshot');

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
