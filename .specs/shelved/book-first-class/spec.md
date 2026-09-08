---
status: shelved
---

# Book First Class

`multiple-books` (shipped) gave a project many books and put the manuscript metadata on
each. A book is still only a name. It cannot say whether it is published, drafted or only
planned, and it cannot say when it sits in world-time. Every list that crosses books hides
which book a row belongs to — most painfully "Referenced in scenes" on a codex entry, which
shows Chapter and Act while eight books each hold a "Chapter 1".

A planner with six books out and two drafted cannot ask the app the question she asks every
day: where am I, and which of this is already in print. See `.scratchpad/series-planner.md`.

## Goals

- A book has a status: published, drafted, planned. Shown wherever a book is named.
- A book has an in-world date range, drawn from the project timeline, not free text.
- Every cross-book list names the book: "Referenced in scenes" first, then search results
  and the dashboard's recent items.
- One scene list for the whole project, with a book filter, beside the per-book list that
  exists now.
- The project navigation reaches any book of any project, not only the first.

## Non-goals

- No real-world publication dates, no store links, no per-book release metadata beyond the
  status. EPUB metadata stays where `multiple-books` put it.
- No reader-facing use of the status. It does not gate export or the share link.
- No book filter inside search — `scoped-search` (expanded) already owns that.
- No series-wide chapter or act list. Scenes are the list she reads; the others can wait.

## Rough approach

- `status` as an enum on `books`, in the style of `SceneStatus`. Date range as two
  nullable event references, in the style of the lifespan columns
  `2026_08_23_000000_add_lifespan_events_to_codex_entries_table`.
- The book column is the work. Find the cross-book readers first — `ReferencingScenes`,
  `SearchResultRow`, `RecentItem` — and give each the book, then the views.
- Series-wide scene list: a second route on `SceneController::index` scoped to the project
  instead of the book. Pagination and the chapter filter already exist; reuse them.
- Open: an in-world range that contradicts the scenes in the book (a scene dated outside
  it). Warn, block, or derive the range from the scenes instead of storing it?
