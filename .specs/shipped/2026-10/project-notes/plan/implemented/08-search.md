---
title: "Task 08 — Notes in project search"
---

# Task 08 — Notes in project search

## Scope

- `SearchDomain::Notes`: label, route key, `notes.edit` / `notes.show`, `carriesBook()` false.
- `ProjectSearch`: `NOTE_FIELDS = ['title' => 'Title', 'body' => 'Body']`, a `notes` column in
  `search()`, and `searchDomain()` support.
- `SearchResults` gains `notes`; the search page and domain page render it.
- Scope filters: notes ignore the book and chapter range, like plotlines and events.

Leaves `SCENE_FIELDS['notes']` in place; 09 removes it with the column.

## Depends on

02.

## Docs

`expanded/architecture.md` → Search.

## Tests

- A note matches by title and by body text inside HTML; accent folding works.
- Domain page for notes paginates.
- Another project's note never appears.
