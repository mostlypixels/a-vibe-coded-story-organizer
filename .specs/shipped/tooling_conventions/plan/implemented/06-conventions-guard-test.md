# Task 06 — Conventions guard test

## Scope

The cheap filesystem test that turns the durable, checked-in half of the feature into a CI guard —
mirrors `tests/Unit/SpecsStatusConsistencyTest` in style (plain `TestCase`, no DB).

**Builds:**
- `tests/Unit/ToolingConventionsTest.php`.

**Does NOT build:** any product code (all written in tasks 01–05). This task is test-only, so it
is the one place `/verify` has nothing new to drive beyond the suite itself.

## Depends on

- Task 01 (conventions file + `CLAUDE.md` pointer must exist).
- Task 02 (gitignore pattern must exist).

(Independent of tasks 03–05; can run as soon as 01–02 are in, but ordered last so the whole
feature's checked-in surface is guarded in one pass.)

## Cases (from `expanded/testing.md`)

1. **Conventions file exists** — `.claude/conventions/tooling.md` is present.
2. **`CLAUDE.md` references it** — `CLAUDE.md` contains the string
   `.claude/conventions/tooling.md`.
3. **No OS privileged** — `assertDoesNotMatch('/prefer PowerShell/i', ...)` over the conventions
   file (locks in G1 — binding decision 1 / Q3).
4. **Ignore pattern present** — `.gitignore` contains `.claude/env.*.local.md`.
5. **No env cache tracked** — glob `.claude/env.*.local.md` and assert none is committed
   (`git ls-files` shows none), defending the never-commit invariant.

Keep it filesystem-only and cheap. The machine-local cache behavior and the hook's inject/prune
logic are covered by task 03's unit test and the manual walk-throughs — do not try to drive the
hook end-to-end from here.

## Key decisions already made

- Ship the test (Q5); include the negative "prefer PowerShell" assertion (Q3).
- Filesystem-only, `SpecsStatusConsistencyTest` style (binding decision 12 keeps it out of app DB).

## Docs to consult

- `expanded/testing.md` (the automatable case list)
- `tests/Unit/SpecsStatusConsistencyTest.php` (style reference)

## Tests this task adds

`tests/Unit/ToolingConventionsTest.php` (above). `composer test` green. Update `CHANGELOG.md`
under `Added`, and add a short `documentation/` note (best-practices or a new `tooling.md` page)
explaining the conventions/cache/hook for junior devs, per the CLAUDE.md documentation rule.
