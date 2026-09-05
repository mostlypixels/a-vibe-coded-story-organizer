# 05 — Remove SmartPunct from the exporter

**Depends on:** 04.

## Scope

- `app/Services/EpubExporter.php` — drop the `SmartPunctExtension` import (line 24) and its
  registration in `converter()` (line 911). Keep Strikethrough, StrikethroughS, TaskList.
- Fix the docblocks at `EpubExporter.php:51` and `:889` that name the SmartPunct converter.
- `database/migrations/2026_07_13_000000_add_frontmatter_to_projects_table.php:17` also names
  it. Update the comment.
- `tests/Unit/Services/EpubExporterTest.php:388` asserts the export converts punctuation.
  **Rewrite the assertion, keep the test**: the exporter now carries through whatever the scene
  already holds.
- Add: a scene with canonical characters exports intact and still passes `validatePackage()`
  well-formedness.

**Not in scope:** the prose documentation under `documentation/` (task 08).

## Key decisions

- An EPUB built from a freshly imported project must be unchanged by this task. If it is not,
  the normalizer disagrees with SmartPunct — that is a task 02 bug, not a reason to keep the
  extension.

## Consult

`expanded/architecture.md` → *Removals*; `expanded/testing.md` → *Regression guards*.
