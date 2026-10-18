---
title: "Task 04 — Card content"
---

# Task 04 — Card content

## Scope

- `CodexAsOfResolver::resolveEntry(CodexEntry $entry, ?Event $moment): array` — attributes and age for one entry, plus a `present` flag. Extract the shared part of `entriesForType()`; `resolve()` keeps its output.
- `GET /codex/{codexEntry}/card?scene=<id>`, name `codex.card`, and `GET /notes/{note}/card`, name `notes.card`. `EditorCardContentController` (`codex`, `note`). HTML partial, no layout.
- Partials `resources/views/editor/partials/codex-card.blade.php` and `note-card.blade.php`. Body only; the card frame (header, buttons) is task 08's `x-editor-card`.

Does **not** add JS or the card frame. Task 08 does.

## Depends on

None.

## Key decisions already made

- Codex card: type badge, cover thumbnail if any, `x-rich-text` description, attribute rows from `codex/partials/as-of.blade.php` markup.
- Unassigned scene (no event): the as-of "undetermined" state.
- Entry not present at the moment: "Not present at this scene's moment", description, no attribute rows.
- `?scene` resolves through `$project->sceneQuery()`; foreign scene is 404. Missing `?scene`: undetermined state.
- Note card: category name, `x-rich-text` body.
- Authorize `view` on the target's project.
- Partials take a ready view model. No resolution in Blade.

Detail: `expanded/architecture.md` → *Controllers*, *Reused services*; `expanded/ui.md` → *Right panel*.

## Tests

- `CodexAsOfTest.php`: `resolveEntry()` agrees with `resolve()` for present entries; `present` false before inception and after termination.
- `tests/Feature/EditorCardContentTest.php`: codex card values as of the scene's event; undetermined state for an unassigned scene; "Not present" state; foreign `?scene` 404; note card body with a resolved `entity:` link; non-owner 403 on both; response has no `<html>`.
