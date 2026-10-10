# Project notes — plan overview

## Execution order

| Task | Purpose |
|---|---|
| 01 | Tables, models, `NoteLinkType`, `HasNotes`, link cleanup on delete |
| 02 | Note CRUD, autosave and History for `note.body`, Notes menu item |
| 03 | Categories: tree, CRUD, move-up delete, starter categories |
| 04 | Links on a note: store, destroy, candidates, picker, `create?link=` |
| 05 | Notes card on the seven linkable types' read and edit pages |
| 06 | Notes index filters and sorting |
| 07 | Table of contents on the note read page |
| 08 | Notes in project search |
| 09 | Move scene notes into notes; remove `scenes.notes`; duplicators copy links |
| 10 | Archive export and import, manifest version 6 |
| 11 | Demo data and documentation |

## Binding design decisions

- Body is rich HTML (`FieldKind::Rich`), cap `note.body` = 500 000.
- Links are many to many through `notables`, same-project only. Linkable: book, act, chapter,
  scene, event (Start and End included), plotline, codex entry.
- A note is in one category or none. Categories nest to depth 3, checked on the server.
- A category filter shows only notes directly in that category.
- Deleting a category moves its notes and sub-categories to its parent (or the root).
- Deleting a linked entity removes the link; the note stays.
- Starter categories (Planning, Research, Continuity, Plot threads, Publishing, Ideas) come
  from `StarterNoteCategories`, called by project create, onboarding and demo install. Never a
  model hook, never on archive import.
- Migrated scene notes: no category, title `Notes: <scene name>`, linked to the scene.
- Duplicating a scene or a codex entry links the copy to the same notes. No note is copied.
- Table of contents on the read page only, when the note has 3 or more headings.
- Notes card on read and edit pages.
- Archive manifest goes to version 6. Versions 4, 5 and 6 import; 4 and 5 map scene
  `notes_file` to a linked note.
- Notes never move between projects and never reach EPUB, the `books/` layer, or a shared scene.

## Invariants every task keeps

- Authorize through `$note->project` / `$category->project` and `ProjectPolicy`; mirror it in
  each Form Request `authorize()`. Non-owner 403 test for every new endpoint.
- A link target is resolved through `NoteLinkType::queryFor($project)`. Never trust a raw id.
- Book, Act and Chapter `deleting` call `Notable::deleteFor()` for cascaded children, next to
  `Revision::deleteFor()`.
- `AutosavableFields::REGISTRY`, `RichTextFields` and `config/revisions.php` stay in agreement.
- `bash scripts/verify.sh` passes at the end of every task.

Docs: `expanded/overview.md`, `data-model.md`, `architecture.md`, `ui.md`, `testing.md`.
