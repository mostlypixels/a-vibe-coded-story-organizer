---
title: "Task 06 — Notes index filters"
---

# Task 06 — Notes index filters

## Scope

- In `NoteController@index`: `linked`, `book`, `search`, and sorting on `title` / `updated_at`
  (`ResolvesIndexSorting`), on top of 03's `category`. `PageSize` pagination.
- Filter bar and `x-sortable-header` in `notes/index.blade.php`; Category and Links columns.
- `ValidatesIndexFilters` for unknown values.

## Depends on

03, 04.

## Key decisions already made

- `book` matches notes linked to the book or to any act, chapter or scene inside it.
- `search` is title only (`LikeSearch::whereContains`). Body search is project search (08).
- Book select shows only when the project has more than one book.

## Docs

`expanded/architecture.md` → Index query; `expanded/ui.md` → Notes index.

## Tests

- Each filter alone and combined with `category`; both sorts; invalid filter values.
- `book`: a note linked to a scene of book A matches A, not B.
