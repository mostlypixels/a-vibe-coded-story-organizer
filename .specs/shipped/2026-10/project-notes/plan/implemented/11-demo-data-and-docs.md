---
title: "Task 11 — Demo data and documentation"
---

# Task 11 — Demo data and documentation

## Scope

- Demo seeders: a few notes in starter categories, one nested category, links to a scene and a
  codex entry. Write `Notable` rows directly (`WithoutModelEvents`).
- Documentation, per `expanded/architecture.md` → Documentation to update:
  `features/notes.md` (new), `features/README.md`, `architecture/README.md` (domain model and
  feature table), `architecture/glossary.md`, `export-import/archive-format.md` (`data/notes/`,
  version 6), `features/rich-text.md` (drop `Scene.notes`).
- Close the shelved `author-notes` spec: note in its file that `project-notes` covers it.
- `CHANGELOG.md` section for the pull request.

## Depends on

01–10.

## Key decisions already made

- `features/notes.md` covers: notes vs codex test, links and the cascade pitfall, categories and
  the move-up rule, starter categories, the 500 000 cap.

## Docs

`.claude/rules/documentation.md`, `.claude/rules/changelog.md`.

## Tests

- Documentation link test passes.
- `migrate:fresh --seed` on the test database runs clean.
