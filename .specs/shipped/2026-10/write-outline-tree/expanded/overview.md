# Overview

## User stories

- As a writer, I open "the second scene of chapter 3" in two clicks, without knowing its name.
- As a writer, I see where the current scene sits in the book.
- As a writer, I can still open a recent scene, and fold that list away when I do not need it.

## Acceptance criteria

- The left panel shows, top to bottom: **New scene**, scene search, a **Recent scenes** section that folds, the **Outline**.
- The outline lists every act, chapter and scene of the current book, in story order.
- Act and chapter labels come from `StoryNumbering` (`actLabel()`, `chapterLabel()`), the same as the breadcrumb.
- Each chapter folds. On page load, only the current scene's chapter is open.
- The current scene row has `aria-current="page"` and the accent style of the recent list.
- On load, the current row scrolls into view inside the panel.
- Scene rows are plain links to `scenes.write`.
- A search query hides the recent section and the outline. An empty query shows them again.
- The open/closed state of the recent section is saved with the editor layout (`sections.recent`).
- Page-load query count does not grow with the scene count.

## Non-goals

- Number search ("3.2"). Search stays name-only.
- Reorder, rename, create, delete from the tree.
- Other books' trees.
- An ARIA `tree` widget with arrow-key navigation (see `ui.md`).
- Saving which chapters are open.
