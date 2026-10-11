---
title: "Task 07 — Docs and changelog"
---

# Task 07 — Docs and changelog

## Scope

- `documentation/export-import/` import guide: run mode from config, the run claim, duplicate rules, the status page.
- `documentation/development/docker.md`: the queue worker runs imports; `pcntl`; `retry_after` vs job timeout; rebuild the image once.
- `CHANGELOG.md`: one dated section for the PR.
- Manual Docker check (`make up`), using the Les Misérables archive:
  - phases update, no 504, link at the end;
  - a double click gives one project;
  - a second upload of the same file warns.

## Depends on

01 to 06.

## Key decisions already made

- Follow `.claude/rules/documentation.md` and `.claude/rules/changelog.md`.

## Tests

- The documentation link test passes.
