# Task 01 — Portable conventions file + CLAUDE.md pointer

## Scope

Write the checked-in rule set and wire it into the always-loaded instructions.

**Builds:**
- `.claude/conventions/tooling.md` (new file, new `conventions/` subfolder).
- One-line pointer added to `CLAUDE.md`.

**Does NOT build:** the gitignore pattern (task 02), any cache file, the hook, or the tests
(task 06 writes `ToolingConventionsTest`).

## Depends on

Nothing. This is the first task.

## What to write in `.claude/conventions/tooling.md`

Cover exactly the sections specified in `expanded/artifacts.md` → *Artifact A*:

1. Native shell **by tool availability, not by name** — no OS privileged. This replaces any
   "prefer PowerShell, fall back to Bash" wording.
2. **Never carry one shell's syntax into the other's tool** (no PS here-strings/`&&`/`$VAR` in the
   Bash tool, and vice-versa). Call out explicitly that this is the platform-independent rule that
   prevents the class of bug that motivated the audit.
3. **Prefer dedicated file/search tools** (Read/Edit/Grep/Glob) over any shell, regardless of OS.
4. **The lockfile decides the package manager** — npm/pnpm/yarn/composer mapping, never guess.
5. **Canonical commands defined once** (test = `composer test`; build/lint/serve likewise);
   resolved values live in the env cache, not scattered prose.
6. **Cache protocol** — consult the local env cache before probing; append after learning;
   re-verify on failure. Cross-reference the cache file (task 02 documents its format).
7. **Machine-id computation, verbatim** — the per-OS block from `expanded/architecture.md`
   (Windows registry / Linux `/etc/machine-id` / macOS `ioreg`, then hash → first 8 hex).

## The `CLAUDE.md` pointer (one line)

Add a single line near the "General" list (alongside existing shell/file-tool guidance), per
`expanded/architecture.md` → *Wiring 1*. It must:
- Point to `.claude/conventions/tooling.md`.
- Include the cache instruction (Q1 decision): *before running any shell probe, read the env
  cache; after learning a tool fact, append it* — so the instruction sits in the always-loaded
  file, not only in the conventions file.

Do **not** inline the full rules into `CLAUDE.md`; keep them single-sourced in the conventions file.

Reconcile the existing `CLAUDE.md` shell line ("PowerShell (primary)…") per the Q1 decision:
leave the auto-populated environment description as machine-specific fact, but ensure the *rule*
lives in `tooling.md`. Do not delete the environment block.

## Key decisions already made

- No OS is privileged (binding decision 1). The string "prefer PowerShell" must not appear.
- Never-cross-syntax rule stated explicitly (binding decision 2).
- Canonical `composer test` (binding decision 4).

## Docs to consult

- `expanded/artifacts.md` (Artifact A required sections + invariants)
- `expanded/architecture.md` (Wiring 1, machine-id recipe)
- `expanded/overview.md` (goal G1)

## Tests this task adds

None directly — the guard test is task 06 (it will assert this file exists, is referenced, and
contains no "prefer PowerShell"). Update `CHANGELOG.md` under `## [Unreleased] → Added`.
