# 09 — Documentation

## Scope

- `documentation/features/writing-progress.md` — a *Challenges* section.
- `documentation/export-import/archive-format.md` — `data/challenges.json` and version 5.
- A row per new class in the *Where things live* table.
- One dated `CHANGELOG.md` section for the pull request.

**Not** in this task: any code change.

## Depends on

01–08.

## Key decisions

Document the rules that are not obvious from the code:

- a challenge is a window plus a target; **nothing about progress is stored**;
- monthly windows are derived per calendar month, never materialised, and the first month is
  not clipped to `starts_on`;
- an optional `ends_on` stops a recurring challenge without deleting its record;
- **par counts finished days**, so day 1 opens at par 0 — and why (the streak rule);
- editing a target re-scores the past, the same as the two project goals;
- fixed windows cap at 366 days; recurring ones do not.

ASD-STE100, tables over prose. Do not cite `plan/` files.

## Consult

`.claude/rules/documentation.md`, `.claude/rules/changelog.md`, the whole `expanded/` set.

## Tests

`tests/Unit/DocumentationLinksTest` must still pass; no new tests.
