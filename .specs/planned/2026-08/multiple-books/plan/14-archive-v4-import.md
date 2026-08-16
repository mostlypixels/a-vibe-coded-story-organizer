# 14 — Import version 4

**Depends on:** 13.

## Scope

- `ImportRules::SUPPORTED_MANIFEST_VERSIONS = [4]`. Version 3 is rejected outright.
- `ALLOWED_DIRECTORIES`: `data/project/`, `data/books/`, `data/timeline/`, `data/codex/`,
  `books/`. `data/acts/` and `book/` are removed. `ALLOWED_FILES` loses
  `data/publication-setting.json`.
- `ProjectGraphImporter` reads books inside the **existing `story` phase**, with a new `books`
  id map. Acts, chapters and scenes hang off the imported book.
- Per-book `publication-setting.json`, keeping its "untrusted, never fatal" posture.
- Both new cover files (book, project) go through the content-sniff gate on bytes alone.

**Not in scope:** any new `ImportPhase` case.

## Key decisions

- **The seeded book is reconciled, never duplicated.** Creating the `Project` fires
  `Project::created`, which seeds one book. The importer **updates that row in place** with the
  archive's lowest-`position` book and maps its id onto it, then creates the rest — the same
  rule the main plotline and the Start/End bookends already follow. Missing this is how a
  one-book import ends up with two books.
- **Do not add a fifth `ImportPhase`.** The phase list is a stored checkpoint contract, and books
  own acts, which is what `story` already means.
- **A `null` book name imports as `null`.** Coercing it to the project name materializes the
  value and breaks the tracking.
- `position` replays verbatim on books as on everything else, never re-derived from directory
  order. Ids are always remapped.

## Consult

`expanded/export-import.md` → *Import*; `documentation/architecture.md` → *Static site import*.

## Tests

- `ImportTest`: a v3 archive is rejected; a one-book archive yields exactly one book (the
  reconciliation); a malformed per-book config logs and imports the content anyway.
- `ImportRoundTripTest`: seed **two** books with different metadata, export through the real
  exporter, import through the real HTTP route, and assert count, order, per-book
  `language`/`author`/`publisher`/`rights`/`isbn`/cover, each book's act tree, and a `null` name
  surviving the round trip. A second import of the same zip still disambiguates the project.
