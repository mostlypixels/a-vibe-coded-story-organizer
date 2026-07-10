# Tooling conventions — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

> [!NOTE]
> The `expanded/` design docs and the `plan/` task files this log references were removed from
> master when the feature was slimmed post-ship (only the portable rules survive). They remain in
> git history and under the `archive/tooling-conventions` tag.

## Feedback & decisions

- **2026-07-10 — Six original open questions accepted as recommended** (Q1–Q6 in
  `expanded/open-questions.md`): CLAUDE.md env block kept as machine-fact + pointer; machine-id
  hostname-fallback; assert absence of "prefer PowerShell"; qualitative trust window (no TTL);
  ship the filesystem test; cache stays in-repo gitignored + stamp.
- **2026-07-10 — PHP SessionStart hook pulled INTO scope** (was out-of-scope in the source spec).
  Grill established the distinction: the *auto-regenerate/scan* hook stays out of scope, but a
  *read/verify/inject* hook is in scope because it closes the enforcement gap (Claude no longer
  has to remember to open the cache). Written in PHP for portability (guaranteed present in a
  Laravel repo, identical across OSes).
- **2026-07-10 — Hook write behavior:** creates a header-only cache when missing, prunes
  foreign-stamped `env.*.local.md`, and is **fail-open** (any error exits 0, injects nothing,
  Claude falls back to Part 1 rules + reactive probing).
- **2026-07-10 — Hook logic is testable:** pure logic in `.claude/hooks/EnvCache.php`, wired via
  `composer.json` `autoload-dev` (dev-only, not shipped app autoload), tested by
  `tests/Unit/EnvCacheTest`.
- **2026-07-10 — Feature developed on branch `feature/tooling-conventions`**, not master — per
  user, this is not going to main yet.
- **2026-07-10 — Scope reduced post-ship: cache + hook removed, rules file kept.** After shipping,
  the user judged the machine-local env cache and the PHP SessionStart hook over-built for the
  payoff (the cache leaned on Claude reliably *appending* facts — the least reliable link — and on
  this machine it stayed header-only, empty, immediately after install). Decision: keep Part 1
  (the portable `.claude/conventions/tooling.md` rules + `CLAUDE.md` pointer), remove everything
  else. Removed in a follow-up commit on this branch: `.claude/hooks/EnvCache.php`,
  `.claude/hooks/session-start.php`, `.claude/settings.json` SessionStart entry, the
  `.claude/env.*.local.md` gitignore pattern, the `ClaudeTooling` `autoload-dev` mapping,
  `tests/Unit/EnvCacheTest`, `tests/Unit/ToolingConventionsTest`, and
  `documentation/tooling-conventions.md`. The `tooling.md` file was trimmed to sections 1–5 (the
  cache protocol + machine-id recipe sections were dropped). All of it remains recoverable from
  git history (shipped in commit `d273397`) if the cache idea is revisited.

## Deviations from the spec/plan

- The source `spec.md` lists a SessionStart hook under *Out of scope*. The plan **overrides** this
  for the read/verify/inject variant only (see decision above); the auto-regenerate variant
  remains out of scope, consistent with the spec's intent.

## Issues → resolutions

- **2026-07-10 — Task 01 — the "prefer PowerShell" phrase leaked into the conventions file it was
  meant to ban.** The first draft explained the new rule by quoting the *old* wording it replaced:
  `"prefer PowerShell, fall back to Bash"`. That literal substring is exactly what binding decision
  1 / task 06's guard forbid appearing anywhere in `tooling.md` (the guard test greps for it
  case-insensitively and does not care that it sits inside a "this replaces…" note). **Root cause:**
  citing the banned phrase to contrast against it still puts the banned phrase in the file.
  **Fix:** reworded the note to the OS-neutral "prefer one shell, fall back to the other" so the
  intent survives without the forbidden token. Verified with a case-insensitive grep (no match).
  Lesson for later tasks/tests: never quote the banned string even as an example.
- **2026-07-10 — Task 03 — `foreignFiles` unit test compared raw path strings across separators.**
  The first run of `test_foreign_files_selects_only_mismatched_caches` failed on Windows: the test
  built expected paths with `/` but `glob()` (called inside `foreignFiles` with a
  `DIRECTORY_SEPARATOR`-joined pattern) returns the OS-native `\`, so `assertSame` saw
  `.../envcache-x/env.copied-...` vs `.../envcache-x\env.copied-...`. **Root cause:** a test-only
  fixture artifact, not a production bug — `glob` legitimately returns backslashes on Windows and
  the class works. **Fix:** normalize both sides to `/` before comparing (a small `$normalize`
  closure) so the assertion is separator-agnostic and passes on any OS.

## Task 04 — SessionStart hook script

**Injection mechanism settled against the installed version (Claude Code 2.1.206).** Checked the
current hooks docs: for SessionStart, plain stdout is added to Claude's context AND a structured
JSON object `{"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"…"}}` on
stdout is consumed. Chose the JSON form (explicit event routing, room to add fields like
`sessionTitle` later). Not registered in `.claude/settings.json` yet — that is task 05, so the
"injection reaches a real running session" end-to-end confirmation cannot be observed until the
hook is wired; it is verified here by driving the script directly and inspecting the emitted JSON.

**Manual walk-through results (all four protocol branches, driven directly with `php`):**

- *Fresh machine (no cache)* → header-only file `env.<host>-8b7f2fa4.local.md` created; injected
  `additionalContext` is the learn-by-doing note ("header-only … Do not pre-scan … probe on
  demand"). Filename `<id8>` equals the in-file stamp `id` (`8b7f2fa4`).
- *Warm cache (facts appended, re-run)* → the full cache body is injected verbatim with a "use
  these already-learned facts and skip re-probing" directive; the existing facts are NOT clobbered
  (the live-stamp check short-circuits the header-only rewrite).
- *Foreign / cloned stamp* → both a purely-foreign-named file AND a correct-named-but-foreign-
  stamped file (hand-edited to `id: deadbeef`) were pruned; the correct cache was regenerated
  header-only. Confirms prune-then-recreate covers the cloned-VM case where the filename coincides
  but the in-file stamp does not.
- *Forced error (unparseable `EnvCache.php`)* → exit code 0, empty stdout. Fail-open confirmed.

**`JSON_UNESCAPED_UNICODE` matters.** Without it, `json_encode` escapes the mid-dot separator
(U+00B7) in the injected stamp to `·`, making the injected context less legible. Flag added so
the stamp reads as `machine: … · id: …` in context. `JSON_UNESCAPED_SLASHES` likewise keeps
`.claude/…` paths readable.

**Load the class by `require`, not the autoloader.** The hook runs as a standalone `php` process
outside Laravel, so it cannot assume `vendor/autoload.php` exists or that `composer dump-autoload`
has run. It `require __DIR__.'/EnvCache.php'` directly (Pint then hoisted a `use ClaudeTooling\
EnvCache;` alias, which is fine — the alias resolves against the required definition at runtime).
`.claude/` is resolved from `__DIR__`, not the process cwd, so the hook is correct regardless of
where Claude Code launches it.

**Fail-open hygiene beyond the try/catch.** Because SessionStart stdout is fed to Claude, a stray
PHP warning would pollute the context, not just be ignored. The script sets `error_reporting(0)` /
`display_errors=0` and wraps the whole body in output buffering, emitting the single JSON object
only on success and discarding the buffer on any `Throwable` — so a mid-run failure injects
*nothing* rather than a half-built payload.

## Notes tests won't capture

- **Windows machine-id path verified live**, not just via the tolerant `/^[0-9a-f]{8}$/` unit
  assertion: ran `EnvCache` under `php -r` on this host and confirmed the id came from the registry
  `MachineGuid` (`reg query`) — the stamp printed `id: 8b7f2fa4` with **no** `(hostname-fallback)`
  marker, and the filename and in-file stamp embedded the same `8b7f2fa4`. The Linux
  (`/etc/machine-id`) and macOS (`ioreg`) branches are unexercised on this box and remain
  manual-verification items for those platforms.
- **`autoload-dev`, not `autoload`:** the `ClaudeTooling\` PSR-4 map went into `autoload-dev` only
  (binding decision 12 / invariant), so the hook logic never enters the shipped app autoload.
  `composer dump-autoload` was required after editing `composer.json` for the namespace to resolve.

## Task 05 — Register the SessionStart hook in .claude/settings.json

**No tracked `.claude/settings.json` existed before this task** — only a gitignored
`.claude/settings.local.json` (personal permission allowlist). Created the tracked file fresh
containing just the `SessionStart` hook (`hooks.SessionStart[0].hooks[0]` = `{type: command,
command: "php .claude/hooks/session-start.php"}`), with no `matcher` (SessionStart is not tool-scoped).
The `update-config` skill normally asks the user to create a missing settings file; here creating it
is the task's deliverable, so it was written directly. The personal allowlist stays in
`settings.local.json` — it was not merged into the tracked file.

**No automated test** (settings wiring is environment config, per the task). Verified by:

- *Hook runs & is valid.* `php .claude/hooks/session-start.php` on this host exits 0 and emits the
  expected single JSON object (`hookSpecificOutput.hookEventName = SessionStart`,
  `additionalContext` = the header-only learn-by-doing note); it created
  `.claude/env.DESKTOP-B7C18KI-8b7f2fa4.local.md`, which `git check-ignore` confirms is ignored and
  `git status` does not show.
- *JSON shape.* Validated the settings file parses and the command string is exactly
  `php .claude/hooks/session-start.php` (jq is not installed on this box; validated with `php -r`
  `json_decode` instead).
- *Fail-open when `php` is absent.* Ran the hook command under an empty `PATH`
  (`env -i bash -c 'php .claude/hooks/session-start.php'`): the shell reports `php: command not
  found`, exits 127, and prints nothing to stdout. Claude Code treats a failed **non-blocking**
  SessionStart hook as non-fatal, so the session still starts and Claude falls back to the Part 1
  rules + reactive probing (binding decisions 8, 9). The command form therefore degrades fail-open
  on a machine without PHP as required.

**End-to-end "injection reaches a live session" is not observable from inside this run** — the hook
only fires when Claude Code starts a *new* session that reads the freshly-written `settings.json`;
the settings watcher does not pick up a file added mid-session. To confirm in a real session: start
a fresh Claude Code session in this repo and check that the SessionStart hook runs (the machine-local
cache is read/created and its body appears in context). If a session shows the hook did not fire,
open `/hooks` once (reloads config) or restart — the file is written correctly.

## Task 06 — Conventions guard test

**Filesystem-only, `SpecsStatusConsistencyTest` style.** Added `tests/Unit/ToolingConventionsTest`
(plain `PHPUnit\Framework\TestCase`, no DB) with the five cases from `expanded/testing.md`: file
exists, `CLAUDE.md` references it, no "prefer PowerShell" (`/prefer PowerShell/i`), gitignore
pattern present, and no `env.*.local.md` tracked. `composer test` green: **341 passed
(1256 assertions)**; the new test on its own is 5 tests / 8 assertions. Pint clean.

### Issues → resolutions

- **`assertDoesNotMatch` does not exist in PHPUnit 11.** The task file (and `expanded/testing.md`)
  specify `assertDoesNotMatch('/prefer PowerShell/i', ...)`, but PHPUnit 11.5 renamed it — the
  first run errored `Call to undefined method ...::assertDoesNotMatch()`. **Root cause:** the older
  assertion name from the spec predates this repo's PHPUnit version. **Fix:** used the current name
  `assertDoesNotMatchRegularExpression` (same semantics). A green suite *would* have hidden this had
  the assertion been written as a plain `assertStringNotContainsString`; running the filter first
  surfaced it.

### Notes tests won't capture

- **Case 5 must use `git ls-files`, not a disk glob.** A valid machine-local cache
  (`.claude/env.DESKTOP-B7C18KI-8b7f2fa4.local.md`) legitimately exists **on disk** in this
  checkout (created by the task-04/05 hook and correctly gitignored). A glob-for-existence would
  therefore wrongly fail. The invariant is "never *committed*", so the test shells out to
  `git ls-files -- .claude/env.*.local.md` and asserts empty output — which passes here precisely
  because the on-disk cache is untracked. The test skips (rather than fails) if `git` is absent so
  it never turns red on a git-less CI image.
- **Gitignore assertion is whole-line anchored** (`/^\s*<pattern>\s*$/m`, pattern `preg_quote`d) so
  a substring buried in an unrelated rule cannot satisfy the guard.
- **Documentation added for junior devs:** new page `documentation/tooling-conventions.md`
  (conventions / env cache / hook, with the copy-safety and fail-open notes) plus a linking
  "Developer tooling" section in `documentation/best-practices.md`; CHANGELOG `Added` entry extended
  to name the guard test and the doc page.
