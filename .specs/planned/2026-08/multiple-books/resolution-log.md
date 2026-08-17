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
- **Two of task 03's four migrations were deferred to the tasks that wire them.** Only
  `add_book_id_to_acts_table` and `drop_moved_project_revisions` landed.
  `move_publication_settings_to_books` breaks `Project::publicationSetting()` at once, and
  `drop_book_metadata_from_projects` breaks `EpubExporter`, `StaticSiteExporter`,
  `ProjectGraphImporter` and the project edit form at once — every one of those is scoped to a
  later task, so landing the schema early meant either a red suite or writing those tasks here.
  The green-suite invariant won. Land each migration with its wiring.
- **The project edit form lost its front matter with no replacement yet.** `rights`,
  `dedication`, `acknowledgements`, `preface` and `postface` left `AutosavableFields`,
  `UpdateProjectRequest` and `projects/edit.blade.php`. The columns still exist and the
  exporters still read them, but nothing writes them until the book edit form exists.
- **`FormRequestCapAgreementTest` lost the form half of its two front-matter tests.** They
  proved the Save form and autosave refuse the same text; no Save form writes a book's front
  matter yet, so both now assert the autosave path alone. Restore the form half with the book
  edit form.
- **`ActController@store` creates the act on the project's first book.** The route still binds
  `{project}` and `Project::acts()` is a read-only `hasManyThrough`, so the controller resolves
  a book itself. It goes away when the story routes nest under `{book}`.
- **The seeders still make one book each.** `data-model.md` asks `LoremIpsumSeeder` to seed a
  two-book project. Held back: with numbering and the routes still project-wide, a second book's
  acts interleave with the first's in every list.
- **`ActController@index`, `ChapterController@index`, `SceneController@index` and
  `StoryController`'s chapter-mode render resolve `StoryNumbering::forBook()` off the project's
  first book**, the same stand-in `ActController@store` already uses. Their `edit` actions (and
  `SharedSceneController`) instead derive the book straight off the entity (`$act->book`,
  `$chapter->act->book`) — no "first book" guess needed once a specific act/chapter/scene is in
  hand. Both go away when the story routes nest under `{book}`.
- **`StaticSiteExporter` needed no `forProject` → `forBook` rename.** It already derives every
  number via `fromActs()` on a tree it has already loaded, never `StoryNumbering::forProject()`
  directly — only its docblock wording ("project-wide" → "book-wide") changed.
- **Task 05's authorization walks were already grown a level.** Task 03 pointed `acts` at
  `book_id` and removed `Act::project()`, so every `$act->project` / `$chapter->act->project` /
  `$scene->chapter->act->project` call site — controllers, Form Requests, `revisionProject()` —
  had to become `->book->project` at once just to keep the app compiling. Task 05's own diff is
  the `RouteContext` rename plus its tests; no controller or Form Request needed touching.
- **`DuplicateEntityRequest` is a third real caller of the old `RouteProject`**, not the two the
  task named (`ProjectNavigation`, `TrackActiveProject`). Renaming the class breaks any caller
  left pointing at the old name, so it was updated too — `RouteContext::resolve($this)->project`.
- **Every move destination narrowed from the project to the book**, in the dialogs *and* in the
  `Rule::exists` behind them: `DestroyActRequest`, `DestroyChapterRequest`,
  `Store/UpdateChapterRequest`'s `act_id`, `Store/UpdateSceneRequest`'s `chapter_id`. The task
  named only the routes, but a book-scoped index with a project-wide rule accepts a POST the UI
  never offers — and the controller then resolves the destination off `$book`, so a request that
  passed validation dies on `findOrFail()`. Moving a whole act between books is task 16's control.
- **`ProjectNavigation` gained `?Book $book`**, which task 09 owns. A Story link cannot be built
  without a book. The fallback here is the route's book, else `$project->books()->first()`; task
  09 replaces it with `$project->lastBook ?? …` and adds `routeBook`/`booksActive`.
  `ProjectController@show` passes the same first-book stand-in for the dashboard's
  "View the story".
- **`Breadcrumbs::storyTrail()` already takes the book** (task 09's line item) — the story route
  names changed here, so it had to. `entityTrail()` now takes `Project|Book`; the timeline, codex
  and tools trails still pass a project.
- **`RecentlyEdited::acts/chapters/scenes` widened to `Project|Book`.** The Story landing page is
  book-scoped; the project dashboard is not.
- **`projects.overview_render_mode` is now dead but still there.** The read and the write moved to
  the book. The column, and its `Project` `$fillable`/`$casts` entries, go with the rest of the
  moved metadata in `drop_book_metadata_from_projects`.
- **The chapters index, create and edit forms take an `$acts` collection**, not `$project->acts`.
  The act `<select>` must offer this book's acts only.
- **`x-delete-with-move-dialog` gained an optional third tier** (`tertiaryCount`/
  `tertiarySingular`/`tertiaryPlural`). The existing shape only expressed "direct children +
  one grandchild level" (Act's chapters + scenes); a book's honest cascade is three levels
  (acts, chapters, scenes). The three-part phrase joins with `Arr::join`, the same "X, Y and
  Z" grammar `ProjectDeleteWarning` already uses. The books **index** dialog still counts acts
  only, matching Act's own index/edit split — the full three-level summary is edit-page only,
  where the extra counts are cheap (one book) rather than N+1 (one row per book).
- **`booksActive` matches the books CRUD routes only** — `projects.books.index`/`create`,
  and the shallow `books.edit`/`update`/`destroy`/`move-up`/`move-down` (named individually;
  a `books.*` wildcard would also sweep up every `books.story.*`/`books.acts.*` manuscript
  route). It deliberately excludes `books.show`: that page is the book home a picker row
  already links to, not the "Manage books" link this flag lights.
- **The picker's "open book" row compares against `routeBook`, not the fallback-including
  `book`.** `ProjectNavigation::$book` still resolves via `lastBook`/first-book even on the
  dashboard (Story links need one), so comparing against it marked a book "active" on a page
  where no book is actually open — the same trap every other `*Active` flag already avoids by
  matching the route, never the fallback. Caught by
  `NavigationTest::test_no_dropdown_item_is_marked_on_home`.
- **`EpubExporter`, `StaticSiteExporter` and `ProjectGraphImporter` still take/return a
  `Project`, unconverted (that's task 12/13's job), but `PublicationSetting` moving off
  `Project` broke every read they did of it.** Each now borrows `$project->books()->first()`
  as a stand-in — the same pattern task 03/05 used for `ActController@store` and friends
  before the story routes nested under `{book}`. `EpubExporter` centralises this in a new
  private `publicationSettingFor(Project $project)`; `StaticSiteExporter` and
  `ProjectGraphImporter` inline it at their one or two call sites each. All three ripple
  away once task 12/13 convert their entry points to take a `Book`.
- **The archive's `data/publication-setting.json` key renamed with the column**
  (`include_project_cover` → `include_book_cover`), even though relocating the file itself
  under `data/books/<id>/` is task 13's job. Leaving the JSON key spelled after a column
  that no longer exists would have been actively misleading; the file's *location* is
  unchanged for now.
- **The Export-ebook config page (`DataTransferController::exportEbook`,
  `export-ebook.blade.php`) resolves `PublicationSetting` off `$selectedProject->books()
  ->first()`, not `lastBook`.** Matches the plain-`first()` stand-in precedent (task 05's
  `ProjectController@show`), not `ProjectNavigation`'s `lastBook ?? first()` — that fallback
  is specifically for nav/breadcrumb continuity, not a generic "current book" resolver.
  Task 12 replaces this with a real book `<select>`.
- **The config page's "Front & back matter" / "Metadata" sections still read `$selectedProject
  ->dedication` / `->author` / etc., not the book's copies.** Correct for now: `EpubExporter`
  itself still renders those PROJECT columns (task 12 moves it to the book), so showing the
  book's copies here would describe an export that doesn't happen yet. Revisit together with
  task 12.
- **The generated `dc:identifier` URN changed from `urn:imagoldfish:project:{id}` to
  `urn:imagoldfish:book:{id}`.** Not spelled out in `export-import.md`, but every other
  identity in the export (title, cover, metadata) now keys off the book, and two books of
  one project must not share a primary identifier. Nothing round-trips this value (the
  importer never reads it back), so the rename is free.
- **The Export-ebook page's picker is a single `<select name="book">`** (query param `?book=`,
  `<optgroup>` per project), not a project `<select>` plus an implicit "first book" the way
  the config-page stand-in worked before this task. `EpubExportRequest`'s payload field
  renamed `project_id` → `book_id` to match, and the picker's `id` attribute renamed
  `epub_project_id` → `epub_book_id`. `PublicationSettingController::backToConfigForm()`
  redirects with `['book' => $book->id]` accordingly — any later task linking back to this
  page must pass `book`, not `project`.
- **The "Front & back matter" / "Metadata" sections on the config page now link to
  `route('books.edit', $selectedBook)`, not the project edit page.** The book edit form
  (task 07) already carries every one of these fields (`dedication`, `author`, `isbn`, …),
  so the stand-in link task 11 left behind is replaced outright rather than carried forward.
- **Every test that round-trips through the REAL `StaticSiteExporter` and the real HTTP
  import route is temporarily skipped (`markTestSkipped`), not rewritten.** Task 13 bumps
  `StaticSiteExporter::DATA_VERSION` to 4 and moves the archive layout (`data/books/`,
  `books/`); `ImportRules`/`ArchiveValidator`/`ProjectGraphImporter` stay on version 3 until
  task 14. A real export now fails the importer's manifest-version and allow-list checks, so
  16 tests across `ImportRoundTripTest`, `PublicationSettingArchiveTest`,
  `WordCountGoalsArchiveTest` and `ArchiveValidatorTest`
  (`test_accepts_a_real_export_archive_*`) are skipped with a one-line reason rather than
  rewritten to assert rejection — task 14 removes the guards, it does not rewrite the tests.
  Two `ImportRoundTripTest` cases (`test_regeneration_fires_on_a_resumed_run_whose_phase_loop_is_empty`,
  `test_an_import_that_fails_before_codex_writes_no_reference_rows`) build their `Import` row
  directly rather than through the real exporter, so they are untouched and still exercise the
  real code paths.
- **`PublicationSettingArchiveTest::importWithInjectedConfig()` still injects its malformed
  config at the old `data/publication-setting.json` root path**, not the new
  per-book location. Moot while the test is skipped; task 14 must move the injection to
  `{bookDir}/publication-setting.json` when it restores the test.

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
- **`Project::acts()` joins `books`, and `books` carries `name`, `position`, `id` and
  `updated_at`.** Every bare column on that relation is now an ambiguous-column error — the trap
  `Project::chapterQuery()`'s docblock already documents, one level up. `pluck('id')`,
  `orderBy('position')`, `select(['id', 'position'])` and `where('name', …)` all had to be
  qualified, in app code *and* in tests. `find()`/`findOrFail()` qualify themselves.
- **`Event::fake()` silences `Project::created`, so the project gets no book.** Two
  `FieldAutosaveTest` cases faked events before building their fixture and then failed on
  `Act::factory()->for(null)` with `Call to undefined method Builder::()`. The fixture is built
  first now. Any test that fakes events and then creates a project hits this.
- **SQLite drops a constrained column happily, but not a NOT NULL one on a populated table.**
  `add_book_id_to_acts_table` deletes the manuscript rows before adding `book_id` — destructive
  by design (pre-V1, reseed), and it also makes a forward `php artisan migrate` work instead of
  erroring on an existing dev database.
- **A bulk script rewrote 30+ files as CRLF and `git diff` said nothing.** `.gitattributes` sets
  `* text=auto eol=lf`, so git normalizes on read and the diff looked clean, while a PHP
  multi-line string literal in `HtmlSanitizationTest` now held `\r\n` and stopped matching the
  saved value. One test failed; the rest of the suite stayed green. Root cause: a Windows text
  mode write translating `\n`. Write bytes, not text, when scripting a mass rename here, and
  check for `\r\n` in `git diff --name-only` afterwards.
- **`Book::displayName()` re-queries the project once per unnamed book when the parent isn't
  chaperoned.** Eager-loading a project's `books` for the picker (`ProjectNavigation::
  projectBooks()`, `otherProjects()`) doesn't wire up each book's inverse `project` relation on
  its own, so calling `displayName()` on an unnamed one (the common case) is an N+1. Fixed with
  Laravel's `chaperone('project')` on both eager loads.
- **The `['layouts.navigation', 'layouts.app']` view composer builds two separate
  `ProjectNavigation` instances per request** — `layouts.app`'s own composer call, and a second
  one when it `@include`s `layouts.navigation`, which shadows the first for that nested view's
  scope. Pre-existing (not a task 09 regression: `ProjectNavigation::$book`'s plain
  `->books()->first()` fallback already ran twice), but it makes counting "books" queries in the
  raw SQL log an unreliable way to guard `projectBooks()`/`otherProjects()`'s memoization —
  other, unrelated queries share the same table and shape. The guard test instead builds a
  `ProjectNavigation` directly off the dispatched request (the `Tests\Unit\BreadcrumbsTest`
  pattern) and counts queries against that one instance.
- **`dropConstrainedForeignId()` does not drop a separate `->unique()` index on the same
  column, and SQLite's rebuild-the-table `DROP COLUMN` then chokes on the dangling index.**
  `move_publication_settings_to_books` failed every test with `no such column: "project_id"`
  while dropping it — `publication_settings.project_id` carried its own unique index
  (`->foreignId('project_id')->unique()->constrained()`) that `dropConstrainedForeignId()`
  never touches (it only drops the FK constraint and the column). Fixed by an explicit
  `$table->dropUnique(['project_id'])` before `dropConstrainedForeignId()`, both directions.
  Any migration dropping a `->unique()`'d foreign id hits this.
- **`EpubExporter` already had a local variable named `$book`** — every method building the
  package held the `Rampmaster\EPub\Core\EPub` library instance in `$book` (a `.epub` "book").
  Converting the service to take a real `App\Models\Book` collided directly with it. Renamed
  every library-instance variable/parameter to `$epub`; `$book` is now reserved for the domain
  model everywhere in the file. Any other exporter/service that talks about a "book" in the
  generic sense should check for the same collision before adding a `Book $book` parameter.
- **`CodexEntry::factory()->for($project)->create()` alone does not make an entry appear in a
  book's appendix**, even with the right type selected and the toggle on. The pre-task-12
  appendix listed the whole project codex, so existing tests never needed a scene to
  reference an entry; the new book-scoped filter joins through `scene_codex_entry`
  (`Scene::codexReferences()` / `CodexEntry::referencingScenes()`), which only
  `SceneReferenceMatcher` populates at runtime — a factory-built scene has no rows there.
  Appendix tests now attach explicitly: `$scene->codexReferences()->attach($entry->id)`.
- **`expanded/export-import.md`'s own `book.json` sample writes `"rights_file":
  "rights.html"`, contradicting its own prose two lines above** ("write it as `rights.txt`,
  never `.html`") and task 13's own line item. Followed the prose/task file: `rights` is a
  plain-text column like `contents`, not rich HTML, so it exports as `rights.txt`. The JSON
  sample in the expanded doc is stale; task 17 (documentation-sweep) should correct it.
