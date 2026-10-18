---
title: "Task 06 — Header extras"
---

# Task 06 — Header extras

## Scope

- Previous and next scene links in the editor header, to `scenes.write`.
- Today's progress in the header, live.
- Scroll position and cursor restore per scene: `resources/js/editor/position.js`.

## Depends on

05.

## Key decisions already made

- **Previous/next:** story order inside the scene's book, across chapters and acts, through `StoryOrder::orderQuery()` on the book's `sceneQuery()`. Two queries (one each way, `limit 1`), not the whole book. Link text: the scene name. Hidden at the ends. Markup like `x-chapter-pager`.
- **Today:** `WordCountHistory::series($project, $today, $today)->writtenInRange()` with `$today = WriterDay::for($project->user)`, same as `ProjectController@show`. With `daily_word_goal`: `x-progress-bar` "Today". Without: the number only.
- **Live update:** today at load + (scene `word_count` from the latest autosave response − scene `word_count` at load). The autosave response already returns `word_count`. No new request. Listen to the event the autosave field emits; add one if none carries the count.
- **Position:** `localStorage` key `editor-position:<scene id>`, value `{scrollTop, from, to}`. Save on scroll (throttled) and selection change. Restore after the editor mounts. Clamp the cursor to the text length. try/catch on every access.

Detail: `00-overview.md` → decisions 14, 15, 16.

## Tests

- `EditorTest.php`: previous/next across a chapter and an act boundary; hidden at book start and end; scenes of another book never appear; today figure equals the dashboard figure; goal bar only with a goal.
- `resources/js/editor/position.test.js`: save and restore; clamp past the end; storage that throws.
- JS test for the live today count: load value plus the delta.
