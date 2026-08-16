# Multiple books — plan overview

The manual for this plan. Never implemented, never moved to `implemented/`.

A `Project` becomes a container for one or more `Book`s. The manuscript
(`Act → Chapter → Scene`) hangs off a **book**; the world (codex, timeline, revisions, word-count
history) stays shared at the **project**.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `naming-cleanup` | Free the word "book" before anything else claims it |
| 02 | `books-table-and-model` | The `books` table, the `Book` model and its invariants, plus the prep task 03 needs |
| 03 | `acts-move-to-books` | Re-point `acts` at `book_id`. The atomic one |
| 04 | `numbering-per-book` | `StoryNumbering::forBook` and every caller |
| 05 | `route-context-and-authorization` | `RouteContext`, and the walks that grow a level |
| 06 | `story-routes-under-book` | Re-nest the story routes under `{book}` |
| 07 | `book-crud` | `BookController`, the books index, delete-with-move |
| 08 | `book-home` | `books.show` |
| 09 | `navigation-and-breadcrumbs` | Picker, `last_book_id` write, breadcrumbs, page title |
| 10 | `search-book-column` | Name the book on act/chapter/scene result rows |
| 11 | `publication-settings-to-book` | Move `PublicationSetting` onto the book |
| 12 | `epub-per-book` | One EPUB per book, with the appendix filter |
| 13 | `archive-v4-export` | Archive version 4 and the `books/` reading layer |
| 14 | `archive-v4-import` | Read version 4; reconcile the seeded book |
| 15 | `revisions-browser-by-book` | Group the browser's manuscript entities under their book |
| 16 | `move-act-between-books` | A move control on the act edit page |
| 17 | `documentation-sweep` | Bring `documentation/` in step |

## Binding decisions

Settled in the grill. Later tasks must not re-litigate them. The full record, with the
reasoning, is `expanded/open-questions.md`.

- **The timeline stays project-scoped.** Plotlines and events are shared across books, like the
  codex. There is exactly one Start/End bookend pair per project, and no book filter on the
  timeline page. This is what keeps `AttributeTimeline` and `CodexAsOfResolver` untouched.
- **Numbering restarts per book.** Act 1 of book 2 is Act 1.
- **`books.name` is nullable.** `displayName()` falls back to the project name;
  `hasOwnName()` gates how visible the book layer is. Every display site calls `displayName()`,
  never `->name`.
- **Adding a second book freezes the first book's name** (a `Book::created` hook).
- **The active book is remembered per project**, on `projects.last_book_id`.
- **The nav keeps one Dashboard link**, still project-level. `books.show` is reached from the
  picker and the book crumb.
- **Search stays project-wide**; act/chapter/scene rows name their book.
- **Archives bump to version 4.** Version 3 is rejected — no migration path.
- **Migrations are destructive, with no backfill.** Pre-V1, the only data is the seed; reseed
  with `php artisan migrate:fresh --seed`.
- **A book's EPUB appendix is filtered** to the entries its own scenes reference.
- **Goals stay project-level.** No per-book progress or goals.

## Invariants every task must preserve

- **Every project holds at least one book.** The `Project::created` hook makes it; deleting the
  last one is a **403**. Nothing may leave a project bookless.
- **Authorization always walks up to the `Project`.** There is no `BookPolicy`. Every new
  endpoint authorizes through `$book->project` (or `$act->book->project`, and so on) in both the
  controller and the Form Request, and ships a test proving a non-owner gets a 403.
- **`position` is per-parent, gappy, and only move-up/move-down writes it.** Books order within
  their project; acts now order within their **book**. `number` stays derived, never stored.
- **A DB cascade bypasses model hooks, so it bypasses file cleanup.** `Book::deleting` must
  purge its own cover and every chapter cover beneath it, exactly as `Project::deleting` and
  `Act::deleting` already do one level up.
- **`scenes.word_count` stays the only stored count.** No count column on `books`.
- **Model hooks are suppressed under `WithoutModelEvents`.** Seeders and the importer set
  `position`, names and book creation explicitly.
- **The suite is green at the end of every task.** No task may hand the next one a red suite.

## Where to look

`expanded/overview.md` for goals and acceptance criteria, `data-model.md` for schema and model
invariants, `architecture.md` for routes, authorization and scoped queries, `ui.md` for the
screens, `export-import.md` for the archive contract and the EPUB, `testing.md` for the test
strategy, `open-questions.md` for every decision and why it went that way.
