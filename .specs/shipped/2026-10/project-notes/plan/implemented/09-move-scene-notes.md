---
title: "Task 09 — Move scene notes into notes"
---

# Task 09 — Move scene notes into notes

## Scope

- Migration per `expanded/data-model.md` → Scene notes migration.
- `Note::titleForSceneNotes(string $sceneName)`; the migration repeats the string rule inline
  (migrations must not depend on app code that can change).
- Remove `notes` from: `Scene` fillable and mutator, `AutosavableFields`, `RichTextFields`,
  `ProjectSearch::SCENE_FIELDS`, `StoreSceneRequest`, `UpdateSceneRequest`, scene create/edit/show
  views, JS tests, `LongNovelSeeder`, `long-novel.json`, `extract-long-novel.php`.
- Scene edit page: the Notes card from 05 takes the old field's place.
- `SceneDuplicator` and `CodexEntryDuplicator` attach the source's note links to the copy.
- Update comments that mention `Scene.notes` (`SharedSceneController`, `EntitySnapshot`,
  `FieldAutosaver`).

Defers: archive changes (10). The column is gone after this task, so here:

- `StaticSiteExporter::addScene()` stops writing `notes_file`.
- `ProjectGraphImporter::importScene()` stops writing `notes`. An old archive's scene notes are
  dropped until 10 maps them to notes.

## Depends on

05.

## Key decisions already made

- Migrated notes: no category, title `Notes: <scene name>`.
- Delete `scene.notes` revision rows in the same migration.
- Duplicate links the copy to the same notes; no note copies.

## Docs

`expanded/data-model.md` → Scene notes migration; `expanded/architecture.md` → Other
`scenes.notes` call sites.

## Tests

- `MoveSceneNotesToNotesTest`: every case in `testing.md` → Migration.
- Scene and codex entry duplicate keep note links.
- Scene search no longer matches on the removed field.
