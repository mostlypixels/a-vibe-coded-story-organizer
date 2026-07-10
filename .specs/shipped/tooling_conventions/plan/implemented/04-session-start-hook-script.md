# Task 04 — SessionStart hook script (read/verify/inject/create/prune, fail-open)

## Scope

The thin executable entry point the SessionStart hook runs. Orchestrates the task-03 `EnvCache`
methods into the runtime protocol; contains as little logic of its own as possible.

**Builds:**
- `.claude/hooks/session-start.php` — invoked as `php .claude/hooks/session-start.php`.

**Does NOT build:** the pure logic (task 03 owns `EnvCache`), the settings.json registration
(task 05).

## Depends on

- Task 03 (`EnvCache` class + autoload).

## Behavior (the runtime protocol from `expanded/architecture.md`)

Wrap the entire body so **any** failure exits 0 with no output — fail-open (binding decision 8):

1. Compute `cacheFilename()` and resolve its path under `.claude/`.
2. **Prune foreign caches:** delete every path in `EnvCache::foreignFiles('.claude')` (gitignored,
   safe). This clears copied/cloned stale caches.
3. **If the correct cache is missing:** create it with a header-only stamp (`stampLine()`, no
   facts). This is identity-stamping, not a scan (binding decision 7).
4. **If it exists but `matchesLiveMachine` is false** (cloned-VM: filename coincided but stamp is
   stale): overwrite with a fresh header-only stamp.
5. **Inject** the current cache body into the session context so Claude has the facts without
   re-probing. Settle the mechanism against the installed Claude Code version — SessionStart hooks
   emit JSON on stdout with an `additionalContext` field (preferred) or plain stdout; verify which
   the installed version consumes and use that. Inject a short "no facts yet, learn by doing" note
   when the cache is header-only.
6. Never scan for tools, never touch the network, never block. Keep total runtime to a single PHP
   process with a couple of cheap OS reads.

## Verification (this is Claude-workflow behavior — verify by driving it)

Per `expanded/testing.md`, walk the protocol manually since it is not a code path a PHPUnit test
exercises end-to-end:
- Fresh machine (no cache) → header-only file created, injected note says learn-by-doing.
- Warm cache → body injected, Claude skips probes.
- Foreign/cloned stamp → pruned/overwritten, fresh stamp created.
- Force an error (rename php, unreadable dir) → session still starts, nothing injected.

Confirm the injected text actually reaches context in the running Claude Code version (the one
integration point that can silently no-op).

## Key decisions already made

- Read/verify/inject, never scan; create header-only; prune foreign (binding decision 7).
- Fail-open always (binding decision 8).
- Portable via PHP; no shell-specific hook (binding decision 9).

## Docs to consult

- `expanded/architecture.md` (runtime protocol state machine, why filename+stamp)
- `expanded/testing.md` (manual walk-throughs, the injection-reaches-context check)

## Tests this task adds

No new PHPUnit test (the pure logic is covered by task 03; the script is orchestration + the
un-unit-testable injection). Record the manual verification results in `resolution-log.md` under
*Issues → resolutions* if anything surprises. Update `CHANGELOG.md` under `Added`.
