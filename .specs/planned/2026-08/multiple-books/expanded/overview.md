# Multiple books — overview

A `Project` becomes a container for one or more `Book`s. The manuscript (acts → chapters →
scenes) hangs off a **book**; the world (codex, timeline) stays shared at the **project**.

## Goals

- A project holds N books, ordered, created/renamed/reordered/deleted by the owner.
- Every existing project keeps working: creation seeds exactly one book, so a one-book
  project reads and behaves as it does today.
- Publication metadata (language, author, ISBN, front/back matter, epub cover) lives on the
  book — a trilogy publishes three distinct EPUBs.
- The codex is shared: one character sheet, referenced from scenes in any book.
- Navigation always answers *which book am I in*, and switching books is one click.
- Both exports carry the book level; import reconstructs it.

## Non-goals

- Moving a book between projects. (Moving *acts* between books is in scope — the
  delete-with-move dialog needs it.)
- Per-book writing goals or a per-book progress chart. Goals and
  `word_count_snapshots` stay project-level, per the source spec.
- Series-level front matter (a shared preface across books).
- Cross-project sharing of a codex.

## User stories

| As a writer | I want |
|---|---|
| starting a trilogy | one project holding three books, sharing one codex and one chronology |
| writing book 2 | the Story menu, overview and numbering to show book 2 only — Act 1 is Act 1 again |
| naming a character in book 3 | the codex entry created in book 1, unchanged, with its scene references updating |
| publishing | to export book 2 alone as an EPUB with its own ISBN and title page |
| archiving | one zip holding every book, restorable into a new project with the books intact |
| finishing a book | to delete a scrapped book and move its acts into another rather than lose them |

## Acceptance criteria

- Creating a project creates exactly one book; the project's book list is never empty.
- Deleting the last remaining book is a **403**, like the main plotline.
- `StoryNumbering` is **book-wide**: act/chapter/scene numbers restart at 1 in each book.
- Every story route binds a `{book}`; every authorization walks `… → book → project`, and a
  non-owner gets a 403 on every new endpoint.
- The project picker shows `Project → Book` two levels; the current book is named in the
  trigger and in the breadcrumb trail.
- One EPUB export = one book. The project `.zip` holds every book under `data/books/`.
- A round-trip export → import reproduces book count, order, per-book metadata, and each
  book's act tree.
- The word "book" means exactly one thing afterwards (see
  [`architecture.md`](architecture.md#the-book-naming-collision)).

## Shape of the change

Large but shallow: one new table, one moved foreign key, one new nesting level in ~15
controllers, both exporters, the importer, the nav, and the breadcrumbs. The risky parts are
the three places that assume "one project = one manuscript": `StoryNumbering`,
`AttributeTimeline`'s Start baseline (see [`open-questions.md`](open-questions.md), Q1), and
the archive contract.
