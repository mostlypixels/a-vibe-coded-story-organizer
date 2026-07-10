<?php

namespace ClaudeTooling;

/**
 * Pure(ish) logic for the machine-local Claude env cache
 * (`.claude/env.<host>-<id8>.local.md`).
 *
 * This is Claude-workflow tooling, NOT application code — it is autoloaded via
 * `composer.json`'s `autoload-dev` (never the shipped `autoload`) so it can be unit
 * tested and reused by the SessionStart hook (task 04) without entering the app runtime.
 *
 * The class deliberately separates two concerns so the tricky copy-detection logic is
 * unit-testable in isolation:
 *
 *   1. Thin OS/filesystem probes (`machineId`, `hostname`, `foreignFiles`) — a few cheap
 *      reads, wrapped so they never throw.
 *   2. Pure string logic (`parseStamp`, `matchesLiveMachine`, `stampLine`, `cacheFilename`)
 *      that a test can drive with fixture strings, no session races.
 *
 * Copy-safety is filename + in-file stamp, BOTH (binding decision 6): the filename is the
 * O(1) lookup, and the in-file stamp is the correctness check for the copied/cloned-VM case
 * where the hostname, machine-id, and the copied file can all coincide. See
 * `.specs/planned/tooling_conventions/expanded/architecture.md`.
 */
class EnvCache
{
    /** The mid-dot separator used in the stamp header (U+00B7). */
    private const SEPARATOR = ' · ';

    /** Memoized short (8-hex) machine id, so we probe the OS at most once per instance. */
    private ?string $shortMachineId = null;

    /** True when {@see machineId()} fell back to hashing the hostname (no OS source). */
    private bool $usedHostnameFallback = false;

    /**
     * Live hostname, filesystem/stamp friendly but not yet sanitized for a filename.
     * Falls back through `php_uname` to a constant so callers always get a usable string.
     */
    public function hostname(): string
    {
        $host = gethostname();

        if ($host === false || $host === '') {
            $host = php_uname('n');
        }

        return $host !== '' ? $host : 'unknown-host';
    }

    /**
     * The first 8 hex chars of the hashed OS machine id — the value embedded in both the
     * cache filename and the in-file stamp so they agree.
     *
     * Per OS: Windows registry `MachineGuid`, Linux `/etc/machine-id`
     * (fallback `/var/lib/dbus/machine-id`), macOS `ioreg`'s `IOPlatformUUID`. If the OS
     * source is unavailable (locked-down registry, missing file, disabled `shell_exec`),
     * it hashes the hostname instead and records that so {@see stampLine()} can mark it
     * `(hostname-fallback)`. Never throws.
     */
    public function machineId(): string
    {
        if ($this->shortMachineId !== null) {
            return $this->shortMachineId;
        }

        $raw = $this->readOsMachineId();

        if ($raw === null || trim($raw) === '') {
            $raw = $this->hostname();
            $this->usedHostnameFallback = true;
        }

        return $this->shortMachineId = substr(hash('sha256', trim($raw)), 0, 8);
    }

    /**
     * The cache filename for the live machine: `env.<safe-host>-<id8>.local.md`.
     * The hostname is sanitized to filesystem-safe characters; the `<id8>` disambiguates
     * two machines that happen to share a hostname.
     */
    public function cacheFilename(): string
    {
        return 'env.'.$this->filesystemSafeHost().'-'.$this->machineId().'.local.md';
    }

    /**
     * The in-file stamp header for the live machine. `$date` is injectable for
     * deterministic tests; it defaults to today. When the machine id came from the
     * hostname fallback, the id is suffixed with `(hostname-fallback)` — the hex is still
     * the first token so {@see parseStamp()} reads it back cleanly.
     */
    public function stampLine(?string $date = null): string
    {
        $date ??= date('Y-m-d');

        $id = $this->machineId();

        if ($this->usedHostnameFallback) {
            $id .= ' (hostname-fallback)';
        }

        return 'machine: '.$this->hostname().self::SEPARATOR
            .'id: '.$id.self::SEPARATOR
            .'detected_on: '.$date;
    }

    /**
     * Extract `machine` / `id` / `detected_on` from a cache file's header stamp, or null
     * when no well-formed stamp line is present (empty file, body-only, or malformed).
     *
     * `machine` + `id` are required (they are the copy-detection check); `detected_on` is
     * captured when present but not required. The `id` is normalized to lowercase hex,
     * ignoring any `(hostname-fallback)` suffix.
     */
    public function parseStamp(string $contents): ?array
    {
        foreach (preg_split('/\r?\n/', $contents) as $line) {
            // Require machine + a hex id, separated by the mid-dot. /u so the multibyte
            // separator matches as a single character.
            if (! preg_match('/^\s*machine:\s*(.+?)\s*·\s*id:\s*([0-9a-fA-F]+)/u', $line, $matches)) {
                continue;
            }

            $detectedOn = null;
            if (preg_match('/detected_on:\s*(\S+)/', $line, $dateMatch)) {
                $detectedOn = trim($dateMatch[1]);
            }

            return [
                'machine' => trim($matches[1]),
                'id' => strtolower(trim($matches[2])),
                'detected_on' => $detectedOn,
            ];
        }

        return null;
    }

    /**
     * True iff the file's stamp names the live machine (host AND re-derived id both match).
     * This is the copy/clone detector: a cache that arrived by zip/rsync/VM-clone carries
     * its origin machine's stamp, so this returns false and the caller regenerates.
     * A file with no parseable stamp is treated as not-live (false).
     */
    public function matchesLiveMachine(string $contents): bool
    {
        $stamp = $this->parseStamp($contents);

        if ($stamp === null) {
            return false;
        }

        return $stamp['machine'] === $this->hostname()
            && $stamp['id'] === strtolower($this->machineId());
    }

    /**
     * List the `env.*.local.md` files in `$claudeDir` whose stamp does NOT match the live
     * machine — the prune candidates the SessionStart hook removes. A file that cannot be
     * read is treated as foreign (safer to prune an unreadable cache than to trust it).
     *
     * @return string[] absolute paths of foreign cache files
     */
    public function foreignFiles(string $claudeDir): array
    {
        $pattern = rtrim($claudeDir, '/\\').DIRECTORY_SEPARATOR.'env.*.local.md';

        $foreign = [];

        foreach (glob($pattern) ?: [] as $path) {
            $contents = @file_get_contents($path);

            if (! is_string($contents) || ! $this->matchesLiveMachine($contents)) {
                $foreign[] = $path;
            }
        }

        return $foreign;
    }

    /** Sanitize the hostname to characters safe in a filename across OSes. */
    private function filesystemSafeHost(): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '-', $this->hostname());
    }

    /**
     * Read the raw OS machine identifier, or null when the source is unavailable.
     * Dispatches on the OS family; never throws.
     */
    private function readOsMachineId(): ?string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => $this->readWindowsMachineGuid(),
            'Darwin' => $this->readMacMachineId(),
            default => $this->readLinuxMachineId(),
        };
    }

    private function readWindowsMachineGuid(): ?string
    {
        $output = $this->runCommand('reg query "HKLM\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid');

        if ($output !== null && preg_match('/MachineGuid\s+REG_\w+\s+(\S+)/i', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function readLinuxMachineId(): ?string
    {
        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $path) {
            if (is_readable($path)) {
                $contents = @file_get_contents($path);

                if (is_string($contents) && trim($contents) !== '') {
                    return trim($contents);
                }
            }
        }

        return null;
    }

    private function readMacMachineId(): ?string
    {
        $output = $this->runCommand('ioreg -rd1 -c IOPlatformExpertDevice');

        if ($output !== null && preg_match('/"IOPlatformUUID"\s*=\s*"([^"]+)"/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Run a system command and return its stdout, or null if the shell is unavailable
     * (e.g. `shell_exec` disabled by `disable_functions`). Wrapped so machine-id derivation
     * degrades to the hostname fallback rather than fataling.
     */
    private function runCommand(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec($command);

        return is_string($output) ? $output : null;
    }
}
