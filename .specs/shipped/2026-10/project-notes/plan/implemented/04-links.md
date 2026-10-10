---
title: "Task 04 — Links on a note"
---

# Task 04 — Links on a note

## Scope

- `NoteLinkController@store` and `@destroy`; `{type}` route constraint from `NoteLinkType`.
- `App\Rules\NoteLinkTarget`: resolves the target through `NoteLinkType::queryFor($project)`.
- `GET /notes/{note}/link-candidates?type=&q=` and `GET /projects/{project}/notes/candidates?q=`
  (`notes.candidates`): JSON, first 20 by name or title.
- `notes.create?link=<type>:<id>` pre-fills one link; the store request validates it.
- Links card on the note edit page and **Linked to** card on the read page.
- `resources/js/notes/linkPicker.js` and its Vitest file. Works in both directions; task 05
  reuses it.

Defers: Notes card on entity pages (05).

## Depends on

01, 02.

## Key decisions already made

- A foreign target is a validation error, not a 404.
- Storing an existing link is a no-op.
- Start and End events are linkable.
- Candidate JSON shape: `[{type, id, label}]` for entities, `[{id, title}]` for notes.

## Docs

`expanded/architecture.md` → Routes, Authorization; `expanded/ui.md` → Note create and edit,
Note read view.

## Tests

- `NoteLinkControllerTest`: every case in `testing.md` → Links (data provider over `NoteLinkType`).
- `linkPicker.test.js`: search, pick, post, error.
