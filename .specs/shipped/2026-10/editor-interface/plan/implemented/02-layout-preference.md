---
title: "Task 02 — Layout preference"
---

# Task 02 — Layout preference

## Scope

- Migration `add_editor_layout_to_users_table`: nullable JSON after `page_width`.
- `User`: fillable, cast `array`.
- `config/editor.php`: `min_width`, `max_width`, `recent_limit` (15), layout defaults.
- `App\Support\EditorLayout` with `resolve()` and `rules()`.
- `PATCH /preferences/editor-layout`, name `preferences.editor-layout`, `EditorLayoutController@update`, `UpdateEditorLayoutRequest`. Returns 204.

Does **not** read the layout in a view. Task 05 does. Does **not** add JS. Task 09 does.

## Depends on

None.

## Key decisions already made

- Shape, limits and clamping: `expanded/data-model.md`.
- The PATCH takes the full shape. It replaces the stored value after `resolve()`.
- Out-of-range width in the request: 422. Out-of-range width already stored: clamped by `resolve()`.

Detail: `expanded/data-model.md`, `expanded/architecture.md` → *Controllers*.

## Tests

- `tests/Feature/EditorLayoutPreferenceTest.php` (model on `PageSizePreferenceTest.php`): saves, 204; 422 on bad width; unknown keys dropped; only the acting user's row changes; guest redirects to login.
- `EditorLayout::resolve()` cases: null, partial, out of range, unknown keys.
