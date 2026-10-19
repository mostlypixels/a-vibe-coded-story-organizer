<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** scripts/db.sh must stay read-only and point at the Docker database. */
class DbScriptTest extends TestCase
{
    public function test_script_opens_the_database_read_only(): void
    {
        $this->assertMatchesRegularExpression('/sqlite3\s+-readonly\b/', $this->script());
    }

    public function test_script_uses_the_database_path_of_the_dev_stack(): void
    {
        preg_match('/DB_DATABASE=(\S+)/', $this->read('docker-compose.dev.yml'), $match);

        $this->assertNotEmpty($match, 'docker-compose.dev.yml does not set DB_DATABASE.');
        $this->assertStringContainsString($match[1], $this->script());
    }

    public function test_script_refuses_to_run_when_docker_is_down(): void
    {
        $this->assertStringContainsString('Docker is not running. Run \`make up\`.', $this->script());
    }

    public function test_script_has_no_flag_that_turns_writes_on(): void
    {
        $this->assertStringNotContainsString('-readwrite', $this->script());
    }

    public function test_settings_allow_the_script_and_not_raw_sqlite(): void
    {
        $allow = json_decode($this->read('.claude/settings.json'), true)['permissions']['allow'];

        $this->assertContains('Bash(bash scripts/db.sh *)', $allow);
        $this->assertNotContains('Bash(sqlite3 *)', $allow);
    }

    private function script(): string
    {
        return $this->read('scripts/db.sh');
    }

    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
