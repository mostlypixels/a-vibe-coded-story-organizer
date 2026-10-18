---
title: "Task 07 — Left panel"
---

# Task 07 — Left panel

## Scope

- `RecentlyEdited::scenes()` gains `string $route = 'scenes.edit'`.
- `resources/views/editor/partials/recent-scenes.blade.php`: 15 recent scenes of the project, `config('editor.recent_limit')`.
- Scene search: `resources/js/editor/search.js`, a thin wrapper on `candidate-search.js`, type `scene` on `projects.link-targets`. Results link to `scenes.write`.

## Depends on

05.

## Key decisions already made

- Empty search shows the recent list; a query replaces it with results; clearing it shows the list again.
- Rows: name, context segments (book, act, chapter), relative time, as `x-recent-list`.
- The current scene row has `aria-current="page"`.
- The JS gets a URL template for `scenes.write` from the server. No route strings built in JS.
- Keyboard: arrows, Enter, Escape, as in the "Link to…" dialog.

Detail: `expanded/ui.md` → *Left panel*; `expanded/architecture.md` → *Reused services*.

## Tests

- `EditorTest.php`: recent list has 15 rows max, newest first, across books, with `scenes.write` URLs; current row marked.
- `RecentlyEdited` default route still `scenes.edit` (dashboard and Story landing unchanged).
- `resources/js/editor/search.test.js`: results map to editor URLs; empty query shows the list.
