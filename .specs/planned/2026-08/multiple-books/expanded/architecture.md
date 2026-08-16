# Architecture

## Routing

Story routes re-nest under `{book}`; everything project-wide keeps `{project}`. Shallow
children (`/acts/{act}/edit`, `/scenes/{scene}/move-up`, …) are **unchanged** — the child id
alone still resolves the route, which is the whole point of shallow nesting.

| Today | Becomes |
|---|---|
| `projects.story.home` / `.overview` / `.overview.mode` | `books.story.*` |
| `projects.acts.index\|create\|store` | `books.acts.*` |
| `projects.chapters.index\|create\|store` | `books.chapters.*` |
| `projects.scenes.index\|create\|store` | `books.scenes.*` |
| — | `projects.books.index\|create\|store`, `books.show\|edit\|update\|destroy`, `books.move-up\|move-down` |

Unchanged, project-scoped: `projects.show/edit/update/destroy`, plotlines, events,
`projects.timeline.home`, the whole codex (`projects.codex.*`, `projects.codex-attributes.*`),
`projects.search.*`, `projects.revisions.index`, `projects.progress`, and all of `admin.*`.

```php
Route::resource('projects.books', BookController::class)
    ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->shallow();
Route::patch('/books/{book}/move-up', [BookController::class, 'moveUp'])->name('books.move-up');
Route::patch('/books/{book}/move-down', [BookController::class, 'moveDown'])->name('books.move-down');
```

## Authorization

`Book` gets **no policy** — it authorizes up to its project like every other child:
`$this->authorize('update', $book->project)`, mirrored in `Store/Update/DestroyBookRequest`.
Every existing story walk grows one level:

| Was | Becomes |
|---|---|
| `$act->project` | `$act->book->project` |
| `$chapter->act->project` | `$chapter->act->book->project` |
| `$scene->chapter->act->project` | `$scene->chapter->act->book->project` |

That includes `revisionProject()` on `Act`/`Chapter`/`Scene`, every `authorize()` call in
`ActController` / `ChapterController` / `SceneController` / `StoryController` /
`SceneShareController` / `FieldAutosaveController` / `RevisionController`, and the matching
Form Requests. Eager-load `book` (or `act.book`) wherever the walk runs in a loop.

## Route context

`App\Support\RouteProject` becomes **`App\Support\RouteContext`** — one walk answering both
questions, because resolving the book *is* the walk that resolves the project:

```php
RouteContext::resolve(Request): self   // readonly ?Project $project, ?Book $book
```

- `{book}` → book + `$book->project`; `{act}` → `$act->book`; `{chapter}` →
  `$chapter->act->book`; `{scene}` → `$scene->chapter->act->book`.
- `{project}`, `{plotline}`, `{event}`, `{codexEntry}`, `{codexAttribute}` resolve a project
  and **no** book.
- The tracking middleware now writes **two** columns: `users.active_project_id` as before, and
  `projects.last_book_id` when the route resolves a book. Both writes keep the existing
  post-`$next()`, 2xx-only gate — that ordering *is* the authorization check.

`ProjectNavigation` gains `?Book $routeBook`, `?Book $book`, and `booksActive`. Its `$book`
fallback, on a project page with no book in the URL, is
`$project->lastBook ?? $project->books()->first()` — so a Codex or Tools detour returns you to
the book you were in, not to book 1.

> [!WARNING]
> Every `route('books.…', $navigation->book)` in the menus is inside the existing
> `@if ($navigation->hasProject())` guard, which is no longer sufficient: a project always has
> a book, but `$navigation->book` is null on a guest/error render. Guard on the book.

## Numbering is book-wide

`StoryNumbering::forProject(Project)` becomes **`forBook(Book)`**; `fromActs(Collection)` is
unchanged (it already takes a tree). Act 1 of book 2 is *Act 1* — numbering restarts per book,
because a reader of book 2 counts from one.

The existing rule still binds one level down: the map must be built from **the whole book's**
tree, never a filtered or paginated subset. Every caller — `ActController@index/edit`,
`ChapterController`, `SceneController`, `StoryController`, both exporters — passes a book.

## Scoped queries

`Project::chapterQuery()` / `sceneQuery()` deepen by one `whereHas` level
(`chapter.act.book` → `project_id`); their docblock reasoning for returning a `Builder` rather
than a relation is unchanged. Add the book-scoped twins:

```php
Book::chapterQuery(): Builder   // Chapter::whereHas('act', fn ($q) => $q->where('book_id', $this->id))
Book::sceneQuery(): Builder
```

Callers split by scope, and the split is the design:

| Consumer | Scope |
|---|---|
| `StoryController`, act/chapter/scene indexes, both exporters | **Book** |
| `ProjectSearch`, `SceneReferenceMatcher::syncProject`, `ProjectController@show` word total, `WordCountSnapshotRecorder` | **Project** |

`Act::scenes()` (the `hasManyThrough` the delete dialog counts) is untouched.

> [!WARNING]
> **`Project::acts()` survives as a `hasManyThrough(Act, Book)` — read-only.** A
> `hasManyThrough` cannot `create()`, and `$project->acts()->create(...)` is live in **six
> seeders and `ProjectGraphImporter`**; every one moves to `$book->acts()->create(...)`. Three
> more sites read `acts.project_id` directly and must walk through books instead:
> `UpdateChapterRequest`'s `Rule::exists`, and `StoreSceneRequest` / `UpdateSceneRequest`, which
> pluck act ids off the project. `ChapterController` and `SceneController` each raw-`join('acts',
> …)` and need a `books` join. Ordering or selecting on the through-relation hits the same
> ambiguous-column trap `chapterQuery()`'s docblock already warns about.

## Word count

`scenes.word_count` stays the only stored count — do not add one to `books`. Book totals are a
`SUM` over `Book::sceneQuery()`, the same shape as the existing act/chapter/project totals, and
`StoryController`'s already-eager-loaded tree still sums in memory for free.
`word_count_snapshots` stays project-level, so the Progress page and the dashboard card are
unchanged.

## Search stays project-wide

The codex is shared and a writer searching a series wants every hit, so `ProjectSearch` keeps
its project scope and its fixed-query-count guarantee. The `Acts` domain query swaps
`where('project_id', …)` for the book walk; `Chapters`/`Scenes` already go through
`Project::chapterQuery()`/`sceneQuery()`.

Act/Chapter/Scene result rows must name their **book**, or a hit in a three-book project is
unlocatable — one extra field on `App\Support\SearchResultRow`, rendered in
`x-search.result-row`. Eager-load the book on those three domains so it stays a fixed query
count.

## Other project-level services

- `ProjectDeleteWarning::countRelations()` gains `'books'`, and `for()` a
  `":count book|:count books"` category. **Hide the category when the count is 1** — a one-book
  project must read as having nothing unexpected to lose, like the main plotline — but show the
  **true** count above that. Deleting a three-book project loses three books, not two, so do
  **not** subtract the auto-created one the way the plotline/event categories do.
- `RecentlyEdited::acts/chapters/scenes` take `Project|Book` and switch the scope query;
  `plotlines`/`events`/`codexEntries` are project-only, unchanged.
- `ProjectRevisionsBrowser::tree()` stays project-scoped; grouping its manuscript entities by
  book is [Q10](open-questions.md).
- `CodexAsOfResolver`, `AttributeTimeline`, `SceneReferenceMatcher` are **untouched** under the
  recommended answer to [Q1](open-questions.md), and substantially rewritten under the other.

## The "book" naming collision

After this feature "book" must name exactly one thing — the model. Three existing uses must be
renamed in the same change, or every later reader has to disambiguate:

| Existing | Becomes |
|---|---|
| `StoryOverviewMode::Book` (`'book'` — render the whole story on one page) | `StoryOverviewMode::Whole` (`'whole'`) |
| `EpubExporter::bookTree()` | `actTree()` |
| the archive's `book/` reading layer | `books/` — see [`export-import.md`](export-import.md) |

`App\Enums\BookLanguage` keeps its name: it is now genuinely per-book.

## Documentation to update

`documentation/architecture.md` (domain model diagram, authorization walk, continuous
numbering, routing, story overview, project picker, breadcrumbs, page title, static export,
import, EPUB export), `glossary.md` (add **Book**; amend Project, Position, Number, Story
overview), `export-format.md` (rewritten — see [`export-import.md`](export-import.md)),
`epub-export.md`, `word-count.md`, `codex.md` (only if [Q1](open-questions.md) goes the other
way), and `CLAUDE.md`'s authorization example.
