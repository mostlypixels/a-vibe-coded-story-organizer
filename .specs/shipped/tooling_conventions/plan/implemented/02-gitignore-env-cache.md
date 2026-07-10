# Task 02 — Gitignore the env cache + document its format

## Scope

Make the machine-local cache untrackable and pin down its on-disk format so tasks 03/04 have a
single spec to read/write against.

**Builds:**
- `.claude/env.*.local.md` pattern appended to `.gitignore`.
- The cache-file format documented in `.claude/conventions/tooling.md` (a short "Env cache
  format" subsection) so the format lives beside the rules, not only in `.specs/`.

**Does NOT build:** any actual cache file (Claude/the hook create it at runtime), the hook, or
the logic class.

## Depends on

- Task 01 (the conventions file must exist to append the format subsection).

## What to do

1. Append to `.gitignore`, near the other local-artifact ignores:
   ```
   .claude/env.*.local.md
   ```
   Confirm `.claude/` and its tracked contents (`skills/`, `conventions/`, `guidelines.md`,
   `hooks/`) remain tracked — only the env caches are ignored.

2. Add an **"Env cache format"** subsection to `.claude/conventions/tooling.md` describing, per
   `expanded/artifacts.md` → *Artifact B*:
   - Filename: `env.<hostname>-<machineid8>.local.md`.
   - Header stamp line: `machine: <host> · id: <id8> · detected_on: <date>`.
   - Body: one dated fact per line (`shell:`, `composer:`, `npm:`, `pnpm:`, `test:`, `serve:` …),
     **including negative facts** (`pnpm: unavailable`).
   - Trust window: qualitative — positive drops on failure, negative clears on user-says-changed.
     No TTL.

## Key decisions already made

- Cache is in-repo + gitignored + in-file stamp (binding decision 10).
- Negative facts recorded; qualitative trust window (binding decision 5).
- Never committed (core invariant).

## Docs to consult

- `expanded/artifacts.md` (Artifact B format + invariants)
- `expanded/architecture.md` (Wiring 2)

## Tests this task adds

None directly; task 06's `ToolingConventionsTest` asserts the ignore pattern is present and that
no `env.*.local.md` is tracked. Update `CHANGELOG.md` under `Added`.
