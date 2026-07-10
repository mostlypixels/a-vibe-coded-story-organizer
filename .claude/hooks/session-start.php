<?php

use ClaudeTooling\EnvCache;

/**
 * SessionStart hook — read / verify / inject the machine-local Claude env cache.
 *
 * Invoked by Claude Code as `php .claude/hooks/session-start.php` (wired in
 * `.claude/settings.json` by task 05). It is the thin runtime entry point that
 * orchestrates the pure {@see EnvCache} logic (task 03) into the
 * startup protocol from `expanded/architecture.md`:
 *
 *   1. Prune foreign-stamped caches (copied/cloned-in `env.*.local.md`).
 *   2. Ensure the correct cache for THIS machine exists and is live-stamped;
 *      create/overwrite a HEADER-ONLY cache if it is missing or stale.
 *   3. Inject the current cache body into the session context so Claude has the
 *      already-learned facts without re-probing.
 *
 * It NEVER scans for tools, touches the network, or blocks — it only reads a
 * couple of cheap OS values (via EnvCache) and does small filesystem writes
 * (binding decision 7).
 *
 * FAIL-OPEN (binding decision 8): the whole body is wrapped so ANY error exits 0
 * with no output. When that happens Claude simply falls back to the portable
 * Part 1 rules (`.claude/conventions/tooling.md`) and reactive probing. Nothing
 * this hook can do is worth blocking or slowing session start.
 *
 * The context-injection mechanism is settled against the installed Claude Code
 * version (2.1.x): SessionStart hooks add stdout to Claude's context, and the
 * structured form is a single JSON object on stdout with
 * `hookSpecificOutput.hookEventName = "SessionStart"` and an `additionalContext`
 * string. We emit that JSON. See `expanded/testing.md` for the manual
 * injection-reaches-context check.
 */

// Fail-open hygiene: never let a PHP warning/notice leak onto stdout (stdout is
// added to Claude's context for SessionStart, so stray output would pollute it).
// All real errors are caught by the Throwable handler below and swallowed.
error_reporting(0);
ini_set('display_errors', '0');

// Buffer everything so a partial write followed by a failure emits nothing:
// on success we echo exactly one JSON object; on any error we discard the buffer.
ob_start();

try {
    // Load the pure logic directly rather than via Composer's autoloader: this
    // hook runs as a standalone `php` process (not inside the Laravel app), so it
    // must not depend on `vendor/autoload.php` existing or `composer dump-autoload`
    // having run. The class sits next to this file.
    require __DIR__.'/EnvCache.php';

    $cache = new EnvCache;

    // `.claude/` is this script's parent directory. Resolving from __DIR__ (not the
    // process cwd) keeps the hook correct regardless of where Claude Code launches it.
    $claudeDir = dirname(__DIR__);
    $cachePath = $claudeDir.DIRECTORY_SEPARATOR.$cache->cacheFilename();

    // 1. Prune foreign caches. These are gitignored and machine-local, so deleting a
    //    stamp that names another machine is always safe — it can only be a copy that
    //    arrived by zip/rsync/VM-clone. This also removes a correct-NAMED-but-stale
    //    file in the cloned-VM case (filename coincided, in-file stamp did not).
    foreach ($cache->foreignFiles($claudeDir) as $foreignPath) {
        @unlink($foreignPath);
    }

    // 2. Ensure this machine has a live-stamped cache. Missing (fresh machine, or the
    //    prune above removed a stale one) OR still-foreign (prune could not delete it)
    //    both get a fresh HEADER-ONLY stamp. This is identity-stamping, not a scan: no
    //    tool facts are written here (binding decision 7).
    $contents = is_file($cachePath) ? @file_get_contents($cachePath) : false;
    $isLive = is_string($contents) && $cache->matchesLiveMachine($contents);

    if (! $isLive) {
        $contents = headerOnlyCache($cache->stampLine());
        @file_put_contents($cachePath, $contents);
    }

    // 3. Inject the cache body so Claude uses the learned facts and skips re-probing.
    //    A header-only cache carries no facts yet, so we inject a short learn-by-doing
    //    note instead (binding decision: reactive, never pre-scanned).
    $additionalContext = buildInjectedContext($cache->cacheFilename(), $contents);

    $payload = [
        'hookSpecificOutput' => [
            'hookEventName' => 'SessionStart',
            'additionalContext' => $additionalContext,
        ],
    ];

    // Discard any buffered noise, then emit exactly the JSON object.
    ob_end_clean();
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (Throwable $e) {
    // Fail-open: swallow everything, emit nothing, exit clean.
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit(0);
}

/**
 * The on-disk content for a freshly created cache: the identity stamp on the first
 * line, then a marker comment explaining that no facts have been learned yet. Kept
 * header-only on purpose — the hook stamps identity, it never probes tools.
 */
function headerOnlyCache(string $stampLine): string
{
    return $stampLine."\n\n"
        ."<!-- No tool facts learned on this machine yet. Append dated facts (positive\n"
        ."     AND negative, e.g. `pnpm: unavailable (2026-07-10)`) below as you probe,\n"
        ."     per .claude/conventions/tooling.md. This file is gitignored. -->\n";
}

/**
 * Build the text injected into Claude's context. When the cache holds learned facts
 * we hand them over verbatim and tell Claude to trust them and skip probing; when it
 * is header-only we hand over a learn-by-doing note so Claude probes reactively and
 * appends what it learns.
 */
function buildInjectedContext(string $filename, string $contents): string
{
    $relativePath = '.claude/'.$filename;

    if (cacheHasFacts($contents)) {
        return "Machine-local tool cache ({$relativePath}) — use these already-learned "
            .'facts and skip re-probing the toolchain. Drop a fact only if a command '
            ."relying on it fails; then re-detect and update the cache.\n\n"
            .trim($contents)."\n";
    }

    return "Machine-local tool cache ({$relativePath}) is header-only — no tool facts "
        .'learned on this machine yet. Do not pre-scan: follow '
        .'.claude/conventions/tooling.md, probe on demand, and append what you learn '
        .'(positive AND negative, each dated) to this file.';
}

/**
 * True when the cache body carries at least one learned fact — i.e. some non-blank
 * line that is neither the stamp header nor a `<!-- ... -->` marker comment.
 */
function cacheHasFacts(string $contents): bool
{
    $inComment = false;

    foreach (preg_split('/\r?\n/', $contents) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            continue;
        }

        // Skip HTML/Markdown comment blocks wholesale.
        if ($inComment) {
            if (str_contains($trimmed, '-->')) {
                $inComment = false;
            }

            continue;
        }

        if (str_starts_with($trimmed, '<!--')) {
            if (! str_contains($trimmed, '-->')) {
                $inComment = true;
            }

            continue;
        }

        // The stamp header line is identity, not a learned fact.
        if (preg_match('/^machine:\s*.+·\s*id:/u', $trimmed)) {
            continue;
        }

        return true;
    }

    return false;
}
