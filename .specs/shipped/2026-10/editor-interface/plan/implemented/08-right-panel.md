---
title: "Task 08 — Right panel"
---

# Task 08 — Right panel

## Scope

- Codex and Notes sections in the right panel, each with a search field (`search.js` from 07, types `codex` and `note`), results, then cards.
- Component `x-editor-card`: title, type, collapse toggle, `x-icon-close-button`, `x-icon-view-link` (new tab), body slot.
- On page load, the server renders the cards: saved `editor_cards` rows that are not `closed`, then the scene's linked notes with no row (collapsed). Bodies from the task 04 partials.
- `resources/js/editor/cards.js` (Alpine `editorCards`): add from a search pick (fetch `codex.card` / `notes.card`), collapse, expand, close. Each change sends the task 03 PUT.

Does **not** collapse the sections themselves or save their state. Task 09 does.

## Depends on

01, 03, 04, 05.

## Key decisions already made

- Each section holds its own cards. New cards go on top.
- Picking an open card does not add a second one. It expands that card and moves focus to it.
- Picking a closed linked note writes `expanded` and shows it.
- Close removes the card from the view and writes `closed`.
- `codex.card` gets `?scene=<current scene id>`.
- Load cards server side in `EditorController@show` with eager loads; no request per card on page load.
- A failed PUT keeps the UI change and shows a small error line. A failed card fetch shows the error in the section.

Detail: `expanded/ui.md` → *Right panel*; `00-overview.md` → decisions 1, 8, 9, 10, 17.

## Tests

- `EditorTest.php`: saved cards render in `updated_at` desc order; `closed` rows are hidden; linked notes without rows render collapsed after saved cards; a closed linked note is hidden; another user's rows do not show; no N+1 (query count stays the same with 1 and 10 cards).
- `resources/js/editor/cards.test.js`: add, no duplicate, collapse, close, the PUT payload for each, failure path.
