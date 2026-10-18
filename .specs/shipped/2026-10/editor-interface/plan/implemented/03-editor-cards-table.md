---
title: "Task 03 — Editor cards table"
---

# Task 03 — Editor cards table

## Scope

- Migration `create_editor_cards_table`.
- Model `App\Models\EditorCard`, enum `App\Enums\EditorCardState`.
- `PUT /scenes/{scene}/editor-cards`, name `scenes.editor-cards.update`, `EditorCardController@update`, `UpdateEditorCardRequest`. Upserts one row. Returns 204.
- Query helper for task 08: the cards of one user and scene, in display order.

Does **not** render cards. Tasks 04 and 08 do.

## Depends on

None.

## Key decisions already made

```text
editor_cards
  id
  user_id         FK users, cascade
  scene_id        FK scenes, cascade
  codex_entry_id  FK codex_entries, nullable, cascade
  note_id         FK notes, nullable, cascade
  state           string: expanded | collapsed | closed
  timestamps
  unique (user_id, scene_id, codex_entry_id, note_id)
```

- Exactly one of `codex_entry_id` and `note_id` is set. Enforce in `booted()` and the Form Request.
- SQLite treats NULLs as distinct in a unique index. Enforce uniqueness in the upsert (`updateOrCreate` on the four keys), not only the index.
- Request: `type` (`codex` | `note`), `id`, `state`. Resolve the target with `LinkTargetType::queryFor($project)`; a foreign id is 404.
- Authorize `update` on `$scene->book()->project`. Mirror it in `authorize()`.
- A `closed` card keeps its row, so a linked note stays hidden. Opening it again writes `expanded`.
- Display order: `updated_at` desc. Task 08 adds linked notes with no row after them.

Detail: `00-overview.md` → decisions 8, 9.

## Tests

`tests/Feature/EditorCardTest.php`:

- Upsert creates, then updates the same row; no duplicate.
- Each state value saves; a bad state is 422; both or neither target is 422.
- Codex entry or note of another project: 404.
- Non-owner of the scene: 403. Guest: login redirect.
- Deleting the scene, the entry or the note removes its rows.
- Rows are per user: another user's row is not changed.
