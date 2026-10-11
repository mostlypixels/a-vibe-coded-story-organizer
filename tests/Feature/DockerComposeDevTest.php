<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** The dev stack must keep hot files off the slow Windows bind mount (#288, #298). */
class DockerComposeDevTest extends TestCase
{
    public function test_compiled_views_live_outside_the_bind_mount(): void
    {
        $config = $this->composeConfig();

        preg_match('/VIEW_COMPILED_PATH=(\S+)/', $config, $match);

        $this->assertNotEmpty($match, 'docker-compose.dev.yml does not set VIEW_COMPILED_PATH.');
        $this->assertStringStartsNotWith('/app', $match[1], 'Compiled views are still on the bind mount.');
    }

    public function test_database_lives_outside_the_bind_mount(): void
    {
        preg_match('/DB_DATABASE=(\S+)/', $this->composeConfig(), $match);

        $this->assertNotEmpty($match, 'docker-compose.dev.yml does not set DB_DATABASE.');
        $this->assertStringStartsNotWith('/app', $match[1], 'The database is still on the bind mount.');
    }

    public function test_database_directory_is_a_named_volume(): void
    {
        $config = $this->composeConfig();

        preg_match('/DB_DATABASE=(\S+)/', $config, $match);
        $directory = dirname($match[1] ?? '');

        $this->assertMatchesRegularExpression(
            '/^\s*-\s*(\w+):'.preg_quote($directory, '/').'\s*$/m',
            $config,
            'No named volume is mounted on the database directory.',
        );
        $this->assertMatchesRegularExpression('/^volumes:\s*\n\s+dbdata:/m', $config, 'The named volume is not declared.');
    }

    public function test_docker_storage_root_is_not_the_native_storage_folder(): void
    {
        preg_match('/LARAVEL_STORAGE_PATH=(\S+)/', $this->composeConfig(), $match);

        $this->assertNotEmpty($match, 'docker-compose.dev.yml does not set LARAVEL_STORAGE_PATH.');
        $this->assertNotSame('/app/storage', rtrim($match[1], '/'), 'Docker shares the native storage folder.');
    }

    public function test_docker_storage_folder_is_ignored_by_git(): void
    {
        $ignore = file_get_contents(dirname(__DIR__, 2).'/.gitignore');

        $this->assertContains('/storage-docker', preg_split('/\R/', $ignore));
    }

    private function composeConfig(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/docker-compose.dev.yml');
    }
}
