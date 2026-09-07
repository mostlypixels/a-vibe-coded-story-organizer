---
status: draft
---

# List jump to position

Pagination fixed the render cost of a long list and took away the only way through it.
Before, a 1600-row scene list was one page: slow, but `Ctrl+F` found chapter 218. Now the
same list is sixteen pages and the browser can only search the hundred rows in front of
it. The control that replaced browser-find is a page number, and a writer does not think
in page numbers. She thinks "show me chapter 218".

The scene list already has the right control and points it the wrong way. Its chapter
filter is the escape hatch, but it defaults to All, and `SceneController::chaptersFor()`
orders the options by `name` — so a 400-chapter serial gets an alphabetical list of
chapter titles, which is no order at all to someone looking for the next one she wrote.

## Goals

- Reach a place in a list by naming it, not by counting pages: a chapter on the scene
  list, an act on the chapter list.
- Order every story-structure dropdown in story order. Alphabetical is never the answer
  for a thing that has a position.
- Remember where the writer was. Opening Scenes should land where she left off, not at
  row 1 of a book she has read a thousand times.
- Landing on a filtered or jumped-to view must keep the sort, the search and the page
  size the lists already honour.

## Non-goals

- No change to pagination itself, the page size preference, or the bar. Those shipped.
- No infinite scroll and no "load more". The problem is aim, not volume.
- No full-text search here. Finding a scene by its words is `scoped-search`.
- No new sort orders.

## Approach

- Fix the ordering first — it is one line and it is wrong today. Story order for chapters
  and acts, the same key the `#` column already sorts by.
- A "go to" control that takes a chapter (or act) and lands on the page holding its first
  row, rather than filtering the list down to it. Filtering hides the neighbours, and the
  writer's next question is usually "and what came after".
- Remembering the last place is a stored preference, like the page size. Where it lives —
  per user, per book, or per list — is open.
- Reuse `x-index-toolbar`; it already submits without a `page`, so a jump resets cleanly.

## Open ends

- Filter to a chapter, or jump to it and keep scrolling? The scene list does the first
  today. The serial writer asked for the second and the two are different features
  wearing one control.
- Whether "where I left off" means the last list position, the last scene edited, or the
  chapter holding it. The three disagree the moment she edits out of order.
- Whether a remembered filter is a surprise. Coming back to a list that is not showing
  everything, with no memory of setting it, is its own support question.
- Whether this belongs on every list or only the ones with story order. Tags and codex
  attributes have no position to jump to.
