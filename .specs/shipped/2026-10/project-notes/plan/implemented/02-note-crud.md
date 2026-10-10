---
title: "Task 02 — Note CRUD"
---

# Task 02 — Note CRUD

## Scope

- Shallow `projects.notes` resource: `NoteController`, `StoreNoteRequest`, `UpdateNoteRequest`.
- Views `notes/index`, `create`, `edit`, `show`. The index is a plain sorted table (title,
  updated); no tree, no filters yet.
- Registry: `AutosavableFields` `'note'`, `RichTextFields` `Note::class => ['body']`,
  `config/revisions.php` cap `note.body` = 500 000.
- Body autosave on edit (`x-autosave-field`), History link.
- Top-level **Notes** link in both project menus; `ProjectNavigation::$notesActive`;
  breadcrumbs and page titles.

Defers: category field and tree (03), links (04), filters (06), table of contents (07).

## Depends on

01.

## Key decisions already made

- Title required, max 255, no autosave.
- Body validation through `AutosavableFields::validationRule('note', 'body')`, never a literal `max:`.
- Notes link sits between Codex and Tools. A plain link, no dropdown.
- Default sort: `updated_at` descending.

## Docs

`expanded/architecture.md` → Routes, Authorization, Revisions and autosave; `expanded/ui.md` →
Navigation, Note create and edit, Note read view.

## Tests

- `NoteControllerTest`: CRUD happy path, non-owner 403 on every action, title and body validation.
- Autosave records a revision; the note History page loads.
- Existing registry agreement tests pass with the new entry.
