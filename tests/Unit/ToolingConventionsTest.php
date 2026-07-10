<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the durable, checked-in half of the portable-tooling-conventions feature:
 * the conventions rule set, its `CLAUDE.md` pointer, the OS-neutrality invariant, and
 * the never-commit-the-env-cache rule. It mirrors {@see SpecsStatusConsistencyTest} in
 * style — plain filesystem assertions, no database — so it runs under `composer test`
 * (and therefore CI) with no extra wiring.
 *
 * The machine-local env cache's runtime behaviour (copy-detection, inject/prune) is a
 * separate concern covered by {@see EnvCacheTest} and the manual protocol walk-throughs;
 * this test deliberately does not drive the hook end-to-end.
 */
class ToolingConventionsTest extends TestCase
{
    /** The checked-in, portable conventions rule set. */
    private const CONVENTIONS_FILE = '.claude/conventions/tooling.md';

    /** The gitignore pattern that keeps every machine-local env cache out of version control. */
    private const ENV_CACHE_IGNORE_PATTERN = '.claude/env.*.local.md';

    private function repoRoot(): string
    {
        // tests/Unit → repo root is two levels up.
        return dirname(__DIR__, 2);
    }

    public function test_conventions_file_exists(): void
    {
        $this->assertFileExists(
            $this->repoRoot().'/'.self::CONVENTIONS_FILE,
            self::CONVENTIONS_FILE.' is missing — the portable tooling conventions must be checked in.'
        );
    }

    public function test_claude_md_references_the_conventions_file(): void
    {
        $claudeMd = file_get_contents($this->repoRoot().'/CLAUDE.md');

        $this->assertNotFalse($claudeMd, 'CLAUDE.md could not be read.');

        $this->assertStringContainsString(
            self::CONVENTIONS_FILE,
            $claudeMd,
            'CLAUDE.md must point at '.self::CONVENTIONS_FILE.' so the conventions are discoverable.'
        );
    }

    public function test_conventions_file_privileges_no_operating_system(): void
    {
        $conventions = file_get_contents($this->repoRoot().'/'.self::CONVENTIONS_FILE);

        $this->assertNotFalse($conventions, self::CONVENTIONS_FILE.' could not be read.');

        // Binding decision 1: the shell is chosen by tool availability, never by OS name. The
        // literal "prefer PowerShell" wording is the OS-biased phrasing this feature replaced, so
        // it must not reappear anywhere in the file — not even quoted as an example of the old rule.
        $this->assertDoesNotMatchRegularExpression(
            '/prefer PowerShell/i',
            $conventions,
            self::CONVENTIONS_FILE.' must not privilege an OS — the phrase "prefer PowerShell" '
            .'is forbidden (the shell is selected by tool availability, not by name).'
        );
    }

    public function test_gitignore_contains_the_env_cache_pattern(): void
    {
        $gitignore = file_get_contents($this->repoRoot().'/.gitignore');

        $this->assertNotFalse($gitignore, '.gitignore could not be read.');

        // Match the pattern as a whole line (ignoring surrounding whitespace) so a substring in an
        // unrelated rule cannot satisfy this guard.
        $this->assertMatchesRegularExpression(
            '/^\s*'.preg_quote(self::ENV_CACHE_IGNORE_PATTERN, '/').'\s*$/m',
            $gitignore,
            '.gitignore must contain the "'.self::ENV_CACHE_IGNORE_PATTERN.'" pattern so no '
            .'machine-local env cache is ever committed.'
        );
    }

    public function test_no_env_cache_is_tracked_in_git(): void
    {
        // A cache legitimately exists on disk (it is machine-local and untracked); asserting on a
        // glob would wrongly fail here. The invariant is that none is *committed*, so ask git what
        // it tracks — `git ls-files` lists only version-controlled paths.
        if (! $this->gitIsAvailable()) {
            $this->markTestSkipped('git is not available; cannot verify tracked files.');
        }

        $tracked = [];
        exec(
            'git -C '.escapeshellarg($this->repoRoot())
            .' ls-files -- '.escapeshellarg(self::ENV_CACHE_IGNORE_PATTERN),
            $tracked
        );

        $this->assertSame(
            [],
            $tracked,
            'A machine-local env cache is tracked in git ('.implode(', ', $tracked).'). These files '
            .'are copy-unsafe and must never be committed — the "'.self::ENV_CACHE_IGNORE_PATTERN
            .'" .gitignore pattern should keep them out.'
        );
    }

    private function gitIsAvailable(): bool
    {
        $output = [];
        $status = 1;
        exec('git --version', $output, $status);

        return $status === 0;
    }
}
