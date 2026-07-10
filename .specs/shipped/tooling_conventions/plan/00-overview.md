# Plan overview — Portable tooling conventions + reactive env cache + PHP hook

This is the plan manual. It is never itself implemented or moved. Each `NN-<slug>.md` task is
independently implementable and verifiable with `composer test` before the next begins.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `portable-conventions-file` | Write the checked-in `.claude/conventions/tooling.md` rule set (Part 1) and the one-line `CLAUDE.md` pointer. |
| 02 | `gitignore-env-cache` | Add the `.claude/env.*.local.md` ignore pattern; document the cache file format. |
| 03 | `env-cache-logic-class` | The testable `.claude/hooks/EnvCache.php` (machine-id, stamp parse, `matchesLiveMachine`, `foreignFiles`) + `autoload-dev` wiring + `tests/Unit/EnvCacheTest`. |
| 04 | `session-start-hook-script` | The thin `.claude/hooks/session-start.php` entry: read/verify/inject, create header-only cache, prune foreign, fail-open. |
| 05 | `wire-settings-hook` | Register the SessionStart hook in tracked `.claude/settings.json` (via the `update-config` skill). |
| 06 | `conventions-guard-test` | `tests/Unit/ToolingConventionsTest`: file exists, `CLAUDE.md` references it, no "prefer PowerShell", ignore pattern present. |

Docs (`CHANGELOG.md`, `documentation/`) are updated within the tasks that change behavior, not
as a separate task — see each task file.

## Binding decisions (do NOT re-litigate)

These were settled in the expanded docs and the grill (2026-07-10). Later tasks treat them as fixed:

1. **No OS is privileged.** Shell is chosen by *tool availability*, never by name. The phrase
   "prefer PowerShell" must not appear in the conventions file (task 06 asserts its absence).
2. **Never cross one shell's syntax into the other's tool** — the platform-independent rule that
   motivated the audit. Stated explicitly in the conventions file.
3. **The lockfile decides the package manager** — never guess.
4. **Canonical commands defined once** (test = `composer test`); resolved values live in the cache.
5. **Cache is learned-by-doing, never pre-scanned.** Negative facts are recorded. Trust window is
   **qualitative** — positive facts drop on command failure; negative facts clear when the user
   says the toolchain changed. No numeric TTL. `detected_on` is human-legible only.
6. **Copy-safety = filename lookup + in-file stamp verification.** Both, never filename-only.
7. **The hook reads/verifies/injects and never scans.** It may create a *header-only* cache and
   prune foreign-stamped caches, but it never runs a tool-detection sweep at startup.
8. **The hook is fail-open.** Any hook error exits 0, injects nothing, and Claude falls back to
   the Part 1 rules + reactive probing. The hook must never block or slow session start materially.
9. **The hook is portable via PHP** (`php .claude/hooks/session-start.php`) — PHP is guaranteed
   present in this Laravel repo and runs identically on Windows/Linux/macOS. No shell-specific hook.
10. **Cache lives in-repo** under `.claude/` (gitignored) + in-file stamp — accept the copy risk.
11. **Identical-clone case accepted.** If hostname *and* machine-id both coincide after a VM clone,
    the copy is undetectable; this is an accepted limitation, not a task.
12. **This is Claude-workflow tooling only.** No `app/`, `routes/`, `database/`, or runtime app
    behavior changes. Hook logic lives under `.claude/hooks/`, autoloaded via `autoload-dev`.

## Core invariants every task must preserve

- **`SpecsStatusConsistencyTest` stays green:** the folder location and `spec.md` `status:` must
  agree at every step. This plan's own folder moves to `.specs/planned/` (status `planned`) when
  `plan-tasks` finishes, and to `.specs/shipped/` when `ship-plan` finishes.
- **No env cache is ever committed.** After task 02, `git status` must never show an
  `env.*.local.md`. Task 06 defends this.
- **The conventions file never names a preferred OS.** Task 01 writes it; task 06 guards it.
- **`composer test` is green after every task** — each task is verifiable in isolation.
- **`autoload-dev`, not `autoload`:** hook tooling must not enter the shipped app autoload.

## Open items left for implementation (non-blocking, don't reshape tasks)

- The exact SessionStart context-injection mechanism (JSON `additionalContext` vs. stdout) is
  settled inside task 04 against the installed Claude Code version.
- PHP machine-id derivation per OS (registry read on Windows, `/etc/machine-id` on Linux, `ioreg`
  on macOS) is task 03's detail; fall back to hashing `hostname` when the source is unavailable,
  stamping `(hostname-fallback)`.
