<?php

namespace Tests\Unit;

use ClaudeTooling\EnvCache;
use PHPUnit\Framework\TestCase;

/**
 * Unit-tests the pure logic of the Claude env cache (copy detection, stamp parse/build).
 *
 * Plain filesystem/string assertions, no database — same style as
 * `SpecsStatusConsistencyTest`, so it runs under `composer test` with no extra wiring.
 * Fixtures for the "live machine" cases are built from the instance's own
 * `hostname()`/`machineId()` so the tests are deterministic on any CI host.
 */
class EnvCacheTest extends TestCase
{
    /** Build a stamp header that names the given cache's live machine. */
    private function liveStamp(EnvCache $cache): string
    {
        return 'machine: '.$cache->hostname().' · id: '.$cache->machineId().' · detected_on: 2026-07-10';
    }

    public function test_parse_stamp_returns_fields_for_well_formed_header(): void
    {
        $cache = new EnvCache;

        $stamp = $cache->parseStamp(
            "machine: DESKTOP-AB12 · id: 3f9c1a7b · detected_on: 2026-07-10\n"
            ."shell: powershell (2026-07-10)\n"
        );

        $this->assertNotNull($stamp);
        $this->assertSame('DESKTOP-AB12', $stamp['machine']);
        $this->assertSame('3f9c1a7b', $stamp['id']);
        $this->assertSame('2026-07-10', $stamp['detected_on']);
    }

    public function test_parse_stamp_returns_null_for_missing_or_malformed_header(): void
    {
        $cache = new EnvCache;

        // Body-only cache: no stamp line at all.
        $this->assertNull($cache->parseStamp("shell: powershell (2026-07-10)\ntest: composer test\n"));
        // Empty file.
        $this->assertNull($cache->parseStamp(''));
        // Malformed: a machine but no id.
        $this->assertNull($cache->parseStamp("machine: only-a-host\n"));
    }

    public function test_parse_stamp_ignores_hostname_fallback_suffix_on_id(): void
    {
        $cache = new EnvCache;

        $stamp = $cache->parseStamp('machine: box · id: deadbeef (hostname-fallback) · detected_on: 2026-07-10');

        $this->assertNotNull($stamp);
        $this->assertSame('deadbeef', $stamp['id']);
    }

    public function test_matches_live_machine_is_true_for_live_stamp_and_false_for_foreign(): void
    {
        $cache = new EnvCache;

        // A file stamped with THIS machine is the live cache.
        $this->assertTrue($cache->matchesLiveMachine($this->liveStamp($cache)));

        // A file stamped with a different host+id is a copy that arrived here — foreign.
        $foreign = 'machine: SOME-OTHER-HOST · id: deadbeef · detected_on: 2026-01-01';
        $this->assertFalse($cache->matchesLiveMachine($foreign));

        // No parseable stamp is also treated as not-live.
        $this->assertFalse($cache->matchesLiveMachine("shell: bash\n"));
    }

    public function test_foreign_files_selects_only_mismatched_caches(): void
    {
        $cache = new EnvCache;

        $dir = sys_get_temp_dir().'/envcache-'.uniqid();
        mkdir($dir);

        try {
            // The live cache — must be EXCLUDED from foreign.
            $matching = $dir.'/'.$cache->cacheFilename();
            file_put_contents($matching, $this->liveStamp($cache)."\nshell: x (2026-07-10)\n");

            // Two caches stamped for other machines — must be SELECTED as foreign.
            $foreignA = $dir.'/env.other-host-11111111.local.md';
            file_put_contents($foreignA, "machine: OTHER · id: 11111111 · detected_on: 2026-01-01\n");

            $foreignB = $dir.'/env.copied-22222222.local.md';
            file_put_contents($foreignB, "machine: COPIED · id: 22222222 · detected_on: 2026-01-02\n");

            // Normalize separators: glob() returns the OS-native separator (backslash on
            // Windows), so compare on a canonical form rather than the raw fixture strings.
            $normalize = static fn (array $paths): array => array_map(
                static fn (string $path): string => str_replace('\\', '/', $path),
                $paths
            );

            $foreign = $normalize($cache->foreignFiles($dir));
            sort($foreign);

            $expected = $normalize([$foreignA, $foreignB]);
            sort($expected);

            $this->assertSame($expected, $foreign);
            $this->assertNotContains(str_replace('\\', '/', $matching), $foreign);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $leftover) {
                @unlink($leftover);
            }
            @rmdir($dir);
        }
    }

    public function test_cache_filename_and_stamp_line_embed_the_same_id(): void
    {
        $cache = new EnvCache;

        $id = $cache->machineId();

        $this->assertStringContainsString($id, $cache->cacheFilename());
        $this->assertStringContainsString('id: '.$id, $cache->stampLine('2026-07-10'));
        $this->assertStringContainsString('detected_on: 2026-07-10', $cache->stampLine('2026-07-10'));
    }

    public function test_machine_id_is_a_non_empty_8_char_hex(): void
    {
        // Tolerant integration-ish assertion: whatever the CI environment exposes (real OS
        // source or the hostname fallback), the id is always a usable 8-hex short id.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', (new EnvCache)->machineId());
    }
}
