# Multiple books — standing issues

**What is still true of the shipped code.** Read this before extending the feature.

Every defect found while implementing was fixed, each with a regression test; the record is
`resolution-log.md` and git history. What remains here are **accepted costs**: consequences of
decisions taken with eyes open, plus one scope gap left open on purpose. They are not bugs and
not a to-do list. Do not "fix" one without re-opening the decision it came from — each says
which.

Distinct from `resolution-log.md` on purpose. That file is the *record of the work*, and its
entries stop being actionable once a task is done. Everything here is **still true of the code
on `master`**.

---

## Accepted costs

### An unlisted entity type fails nothing

`ProjectRevisionsBrowser::GROUPS` and `AutosavableFields::REGISTRY` are guarded in one direction
only: every `GROUPS` slug must be a registered slug, but a registered slug **missing** from
`GROUPS` breaks no test. Such a type keeps its history — the pages under `/revisions/<slug>/{id}`
still resolve — while nothing in **Tools ▸ Revisions** ever links to it.

That is exactly how `book` shipped unlisted and had to be added late. Register a new autosaving
type and you must add its `GROUPS` row by hand; no failure reminds you.

### The seeded demo data has no multi-book project

`expanded/data-model.md` asked `LoremIpsumSeeder` to seed one project with **two** books.
Every seeder still creates exactly one. It was held back while numbering and the story routes
were still project-wide (a second book's acts interleaved with the first's in every list), and
that reason is gone — the routes nest under `{book}` now — but the seed was never revisited.

So `php artisan migrate:fresh --seed` exercises the book layer only in its **invisible** state:
no picker second line, no breadcrumb crumb, no numbering restart, no "move this act to another
book" destination. Anyone verifying those in a browser has to add a second book by hand first.

### The books index dialog counts acts, not the whole cascade

Deleting a book from its **edit** page shows the honest three-level cascade (acts, chapters,
scenes). The **index** dialog counts acts only. Counting the two deeper levels per row is one
extra query per book — N+1 on a page whose whole job is listing books — and this is exactly the
index/edit split `Act` already makes for the same reason.

### Two books with the same name are indistinguishable in the tab strip

`PageTitle` renders `"<book display name> - <app name>"`, book name first, because browser tabs
truncate from the right. Two projects each holding a "Volume One" therefore produce identical
tabs. Putting the project name in front of the truncation as well is what the leading-name rule
exists to prevent. Named as a cost in `expanded/ui.md` before any code was written.

### A book's EPUB appendix follows the prose, not the author's intent

The appendix is filtered to the codex entries **this book's scenes reference**, through
`scene_codex_entry` — a pivot `SceneReferenceMatcher` derives from the scene text. An entry the
author wants in volume two's appendix but never names in volume two's prose does not appear,
and there is no manual override.

The alternative was an unfiltered appendix, which prints book three's characters in book two's
published file — a spoiler in something a reader downloads. That trade was reopened once during
the grill and settled the same way.

> [!NOTE]
> **`bash scripts/verify.sh` fails intermittently on Windows** with
> `ZipArchive::close(): Renaming temporary file failed: Permission denied` inside
> `EpubExporter`, roughly one full run in three. The cause is environmental — parallel
> `paratest` workers write many temp zips at once and a Windows file lock (indexer or
> antivirus) still holds one when `close()` renames it. The failing test always passes on its
> own. Re-run before hunting a regression in EPUB code you did not touch.

---

## Not costs — decisions, recorded elsewhere

Don't re-litigate these either; they are settled, with reasoning, in
`plan/00-overview.md` → *Binding decisions* and `expanded/open-questions.md`:

* The **timeline and the codex stay project-scoped** — one Start/End bookend pair per project,
  no book filter on the timeline page.
* **Numbering restarts per book.** Act 1 of book 2 is Act 1.
* **`books.name` is nullable**, `displayName()` falls back to the project name, and a
  `Book::created` hook freezes an unnamed sibling's label when the second book arrives.
* **The active book is remembered per project** on `projects.last_book_id`.
* **Search stays project-wide**; act/chapter/scene rows name their book instead.
* **Archives bump to version 4**, and version 3 is rejected — no migration path.
* **Migrations are destructive, with no backfill.** Pre-V1, the only data is the seed.
* **Goals and the word-count history stay project-level.** No per-book progress.
* **The nav keeps one Dashboard link**, still project-level; `books.show` is reached from the
  picker and the book crumb.
