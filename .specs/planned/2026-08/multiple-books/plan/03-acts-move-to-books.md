# 03 — Move `acts` onto `book_id`

The atomic one. The migration breaks every act call site at once, so schema, models, factory,
seeders, importer, form requests, raw joins and the test sweep all land together or the suite
stays red.

**Depends on:** 02.

## Scope

- `add_book_id_to_acts_table` — add `book_id` (NOT NULL, cascade), drop `project_id`.
- `move_publication_settings_to_books`, `drop_book_metadata_from_projects`,
  `drop_moved_project_revisions`.
- `Act::book()`, `Book::acts()`; `Act::siblingScopeColumn()` → `book_id`; the `creating` hook
  scopes `max(position)` to `book_id`.
- `Project::acts()` becomes `hasManyThrough(Act, Book)` — **read-only**.
- `Book::chapterQuery()` / `Book::sceneQuery()`; `Project::chapterQuery()` / `sceneQuery()`
  deepen by one `whereHas` level.
- Every `$project->acts()->create(...)` moves to `$book->acts()->create(...)` — **six seeders
  and `ProjectGraphImporter`**. A `hasManyThrough` cannot `create()`.
- `UpdateChapterRequest`'s `Rule::exists` on `acts.project_id`, and `StoreSceneRequest` /
  `UpdateSceneRequest`, which pluck act ids off the project, all walk through books.
- The raw `join('acts', …)` in `ChapterController` and `SceneController` gains a `books` join.
- `ActFactory` swaps `project_id` for `book_id`.
- The test sweep: ~55 files. Use task 02's `TestCase` helper where a test never mentions books;
  write `Book::factory()` explicitly where the test is *about* structure.

**Not in scope:** numbering (task 04) — `StoryNumbering::forProject` keeps working through the
new `Project::acts()` and stays project-wide until then. Routes and authorization walks are
tasks 05–06.

## Key decisions

- **No `acts.project_id` alongside `book_id`.** Two paths to the same owner drift the first time
  an act moves between books, which task 16 makes a real action.
- Ordering or selecting on `Project::acts()` hits the ambiguous-column trap `chapterQuery()`'s
  docblock already documents — qualify the columns.
- Destructive, no backfill. Reseed after migrating.

## Consult

`expanded/data-model.md` → *Changed foreign keys*, *Migrations*;
`expanded/architecture.md` → *Scoped queries* and its warning on `Project::acts()`;
`expanded/testing.md` → *The blast radius*.

## Tests

- The whole suite green. That is the deliverable.
- `ActTest`: `position` scopes to the book — two books each start at 1.
- `WordCountTest`: the project total still sums across the tree, now through books.
- Nothing new asserts numbering yet; task 04 owns that.
