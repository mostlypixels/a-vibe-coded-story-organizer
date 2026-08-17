# Open questions — resolved

> [!IMPORTANT]
> Every question below is **settled**. This file is the record of what was asked and what was
> decided, not a live agenda. The binding statement of each decision is
> `plan/00-overview.md` → *Binding decisions*; the reasoning behind the changed ones is in
> `resolution-log.md` → *Feedback & decisions*.

## Q1 — Does the Timeline move to the book?

**Decided: no.** Plotlines and events stay project-scoped, shared like the codex. Book 2's
events sit later on the same clock. The timeline page gains **no** book filter either — it was
offered and declined.

Why it could not go the other way: a codex attribute value anchors to an `Event`
(`start_event_id`), and every valued (entry, attribute) pair must hold a baseline at
`Project::startEvent()`. Per-book events give a project many Start bookends and no single
answer to "what is Alice's age". `AttributeTimeline`, `CodexAsOfResolver`,
`Project::startEvent/endEvent` and `WithinEventWindow` all assume one chronology, and a shared
clock is strictly more expressive than per-book ones.

## The rest

| # | Question | Decision |
|---|---|---|
| Q2 | Two covers — project (dashboard card) and book (EPUB)? | **Yes.** Different jobs. |
| Q3 | Persist the last-opened book? | **Yes — `projects.last_book_id`.** Per project, not per user, so switching projects returns you to the right book in each. Reversed from the first recommendation: the Codex detour otherwise dumps you back in book 1. |
| Q4 | Accept v3 (single-book) archives on import? | **No.** Bump to 4, reject 3. Pre-V1, re-exporting is free. |
| Q5 | Is search project-wide or book-scoped? | **Project-wide**, with the book named on act/chapter/scene rows. |
| Q6 | Does `books.show` earn its place? | **Yes.** The picker and the book crumb both need a per-book target, and the overview is a reading page. |
| Q7 | Appendix: whole project codex, or this book's entries? | **This book's entries**, via `scene_codex_entry`. A spoiler in a published EPUB is a defect. Falls back to an empty appendix, never to the full codex. |
| Q8 | Does `Book` get a `description`? | **Yes.** |
| Q9 | Book-wide numbering, or continuous across a series? | **Book-wide.** A reader of book 2 counts from Act 1. |
| Q10 | Group the Revisions browser by book? | **Yes, in scope**, as a late task. |
| Q11 | What is the auto-created book called? | **Nothing.** `books.name` is nullable; `displayName()` falls back to the project name. Reversed from "copy the project name": a copy drifts the moment the project is renamed. |
| Q12 | A standalone "move act to another book" control? | **Yes, in scope**, as a late task. |
| Q13 | Per-book writing goals / progress? | **No.** Goals and `word_count_snapshots` stay project-level. |
| Q14 | Rename `StoryOverviewMode::Book` now? | **Yes**, and first — task 1, so the word "book" is free before anything else claims it. |

## Raised by the grill, not in the original list

| Question | Decision |
|---|---|
| What happens to the unnamed first book when a second is added? | A `Book::created` hook **copies the project's current name onto the first book**, so it stops tracking the project. One write, at the moment the project stops being a one-book project. |
| Is the name field required? | **Optional on the sole book** (so you can edit its ISBN without a forced rename), **required from the second book onward** (or two books show the same label). |
| How visible is the book layer in a one-book project? | Visible only when the book **has its own name** — one predicate, `Book::hasOwnName()`, driving the picker's second line, the book crumb and the page title. Derived from the name, not from a count, so a deliberately named sole book still shows. |
| With two home pages, what does the nav's Dashboard link do? | Stays project-level. `projects.show` gains a book list. `books.show` is reached from the picker and the book crumb. No second nav item. |
| Can the acts FK move be split? | **No.** The migration breaks every act call site at once. Task 3 is atomic; task 2 absorbs the prep so task 3 only re-points existing code. |
| Which `books/` reading layout? | A table of contents **per book**. `chapterHref()` stays `%02d/%02d.html` relative to the book's own TOC, so the file-identity rule survives. |
