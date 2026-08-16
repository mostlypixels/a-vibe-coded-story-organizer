# Multiple books — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

Resolved in the planning grill, 2026-08-16. The full question-by-question record is
`expanded/open-questions.md`; these are the ones that **changed** the expanded design.

- **The timeline stays project-scoped**, against the source spec's "the story and timeline are
  bound to the book itself". A codex attribute value anchors to an `Event`, and every valued
  (entry, attribute) pair needs a baseline at `Project::startEvent()`. Per-book events give a
  project many Start bookends and no single answer to "what is Alice's age". A book *filter* on
  the timeline page was offered as the presentation-only alternative and declined too.
- **`books.name` is nullable, with `displayName()` falling back to the project name.** The
  expansion first proposed copying the project's name at creation. That drifts: rename the
  project and the picker shows two different names for a one-book project.
- **A `Book::created` hook copies the project's name onto unnamed siblings.** Without it, a
  later rename of the project to a *series* name retitles the first volume.
- **`Book::hasOwnName()` gates the book layer's visibility** — picker second line, breadcrumb,
  page title. Derived from the name rather than a book count, so a deliberately named sole book
  still shows the layer and no page pays for a `count()`.
- **The active book is remembered per project** (`projects.last_book_id`), reversing the
  expansion's "do not store it". Falling back to the project's first book dumps the writer in
  book 1 after every Codex or Tools detour — a common loop, not an edge case.
- **A book's EPUB appendix is filtered** to the entries its own scenes reference. Initially
  declined, then included: an unfiltered appendix puts book 3's characters in book 2's published
  file.
- **The Revisions-browser grouping and the move-act control are in scope**, not deferred.
- **Task 03 is atomic.** Splitting it was offered and declined: the FK migration breaks every act
  call site at once, so a split means two commits the test suite cannot verify.

## Deviations from the spec/plan

- **`ProjectDeleteWarning` counts books truthfully.** The expansion said to show `books_count - 1`,
  copying the main-plotline rule. That is wrong — deleting a three-book project loses three
  books. The category hides at one book instead, and shows the true count above that. Caught
  during the grill, before any code.
- **The `'project'` slug keeps its five moved fields for now.** Task 02 was to strip them from
  `AutosavableFields` and re-key `config/revisions.php`, but `Store/UpdateProjectRequest` and the
  project edit form still write them, and both are out of that task's scope. `book.*` caps sit
  beside the `project.*` ones instead of replacing them; task 03 drops both with the columns.
- **`Book::deleting` purges only the book's own cover.** The chapter-cover sweep needs
  `acts.book_id`, so it lands with the FK in task 03, together with its `Storage::fake` test.
- **`BookFactory` omits `position`** (`data-model.md` said 1). `Project::created` already makes
  the first book at position 1, so a hard-coded 1 collides on `Book::factory()->for($project)`.
  The `creating` hook assigns it, exactly as `ActFactory` relies on.

## Issues → resolutions

- **`2026_07_22_000002_backfill_baseline_revisions` reads `AutosavableFields::REGISTRY` live**, so
  registering the `book` slug made a July migration query a table an August migration creates.
  Every test failed with `no such table: books` before a single book test ran. The migration now
  skips a registered model whose table does not exist yet (`Schema::hasTable`) — rows written
  after it get their baseline from the live write path anyway. Any later slug added to the
  registry hits the same trap.
- **`RevisionController::EDIT_ROUTES` has no `book` entry** until task 07 names `books.edit`, so a
  hand-typed `/revisions/book/{id}` is a 500 rather than a 404 in between. No UI reaches it: no
  book field is editable yet.
