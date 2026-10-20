---
status: shipped
shipped: 2026-10-20
planned: 2026-10-20
expanded: 2026-10-20
---

# Write Outline Tree

## Problem

The Write page left panel gives only a scene-name search and a recent list. To open "the second scene of chapter 3", the writer must remember the scene's name. The panel shows no structure of the book.

## Goals

- An outline tree in the left panel: act › chapter › scene, in story order, for the current book.
- Labels use the same numbering as the editor breadcrumb (`StoryNumbering`).
- Chapters fold open and shut. The current scene's chapter starts open, and the current scene is marked.
- Scene rows are real links, so the leave guard flushes a pending save first (as the recent rows do now).
- A search query shows results in place of the tree. An empty query shows the tree again.
- Recent scenes move into a collapsible card in the same panel.
- Keyboard accessible, semantic tree markup.

## Non-goals

- Search by number ("3.2"). Search stays name-only.
- Reorder, rename or create from the tree. The outline pages do that.
- Swap the editor in place. A scene switch stays a full page load (see `EditorController`).
- Trees for other books at the same time.

## Rough approach

- `EditorController::show` already loads `chaptersInStoryOrder()`. Extend it to load scenes, and eager-load them.
- Recent card: follow the open/shut pattern of the right panel's `card-section`, and keep its state in the editor layout preference.
- Closed chapters keep a long serial usable: only open chapters show their scenes.

## Open questions

- Book switcher in the tree for multi-book projects, or the current book only?
- Hide the act level when the book has one act?
- Word counts on scene or chapter rows?
- Remember which chapters are open, or open only the current one on each load?
