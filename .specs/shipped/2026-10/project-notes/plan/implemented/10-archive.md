---
title: "Task 10 — Archive export and import"
---

# Task 10 — Archive export and import

## Scope

- `StaticSiteExporter::addNotes()`: `data/notes/categories.json`, `data/notes/<id>-slug/note.json`,
  `body.html` via `addFieldFile()`. Manifest `version` 6.
- `ImportRules::SUPPORTED_MANIFEST_VERSIONS = [4, 5, 6]`.
- `ProjectGraphImporter::importNotes()` runs after story, timeline and codex; categories parents
  first; links remap through `$idMaps`.
- Versions 4 and 5: a scene `notes_file` becomes a note linked to the scene, titled with
  `Note::titleForSceneNotes()`.
- `ProjectImporter` phases: add notes as the last phase.

## Depends on

03, 04, 09.

## Key decisions already made

- Link `type` in JSON is the `NoteLinkType` key, never a class name.
- A link to an id missing from `$idMaps` is an `ImportValidationException`.
- Import never calls `StarterNoteCategories`.
- Notes stay out of the `books/` reading layer and EPUB.

## Docs

`expanded/architecture.md` → Export and import; `documentation/export-import/archive-format.md`.

## Tests

- Round trip: nested categories, notes, a link of each type, ids remapped.
- Version 5 fixture with a scene `notes_file` imports as a linked note.
- Imported project has only the archive's categories.
- No notes in `books/` or in EPUB output.
