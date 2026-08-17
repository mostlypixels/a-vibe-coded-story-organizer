# Testing

## New: `tests/Feature/BookTest.php`

Modelled on `ActTest` — the closest sibling. Cover:

- CRUD happy path via `route()` helpers, `actingAs($user)`, `RefreshDatabase`.
- **Authorization**: non-owner gets 403 on index/create/store/show/edit/update/destroy and both
  move routes.
- **Validation**: missing name → `assertSessionHasErrors('name')`.
- **`position` invariant**: second book gets position 2; `move-up`/`move-down` swap adjacent
  positions and are a no-op at the ends.
- **Creating a project creates exactly one book**, named after the project.
- **The last book cannot be deleted** — 403, and the book still exists.
- **Delete-with-move**: acts reassign to the destination book, appended after its existing
  acts, relative order preserved, no position collision. Without a destination, the acts and
  their whole subtree cascade.
- **Cover purge**: deleting a book removes its own cover file *and* every chapter cover
  beneath it (the FK cascade bypasses `Chapter::deleting`) — assert with `Storage::fake`, the
  same shape as the existing project/act cover tests.
- **Word-count snapshot** is re-recorded after a book delete.

## New assertions in existing suites

| Suite | Add |
|---|---|
| `StoryNumberingTest` | two books in one project number **independently** — book 2's first act is Act 1, its first scene Scene 1 |
| `ImportRoundTripTest` | seed **two** books with different metadata; assert count, order, per-book `language`/`author`/`publisher`/`rights`/`isbn`/cover, and each book's act tree — the metadata assertions are new coverage, not a port (they were never in an archive) |
| `ImportTest` | a v3 archive is **rejected**; the seeded book is reconciled, not duplicated (one-book archive → one book) |
| `ExportTest` | `data/books/<id>-slug/…` layout, `publication-setting.json` inside the book dir, `books/NN/NN/NN.html` reading layer, `books/index.html` |
| `EpubExportTest` | one book exports without the other's content; `defaults_v1_regression` re-baselined deliberately |
| `SearchTest` | an act/chapter/scene hit names its book; the fixed-query-count guard still passes with the book eager-loaded |
| `NavigationTest` | the picker renders project → book two levels; the open book is marked active; no N+1 across the two menus |
| `BreadcrumbsTest` / `BreadcrumbsComponentTest` | book crumb on book-scoped pages, absent on codex/timeline/tools |
| `PageTitleTest` | book page titles by book, project page by project, off-route still bare app name |
| `WordCountTest` | book totals; project total sums across books |
| `ProjectTest` | delete warning counts books (excluding the auto-created one) |
| `RecentlyEditedTest` | book-scoped manuscript lists |
| `AutosavableFieldsAndHasRevisionsTest` | the `book` slug and its six fields; `project` down to `description` |

## The blast radius

73 test files call `Project::factory()`, ~55 build an act tree. Every one of those breaks the
moment `ActFactory` swaps `project_id` for `book_id`. Two ways through, and the choice matters
for how the plan is sequenced:

- **Mechanical**: `Act::factory()->for($project)` → `->for(Book::factory()->for($project))`.
  Honest, and it makes every test say which book it means.
- **A helper on the base `TestCase`** (e.g. `projectWithBook(): array`) for the many tests that
  only need "a project with somewhere to put acts".

Prefer the helper for tests that never mention books, the explicit form wherever the test is
*about* structure. Do this as **one dedicated task before any behaviour change** — a red suite
of 55 files hides real regressions in the tasks that follow.

## Not needed

No migration test. The existing `*MigrationTest` files exist because those migrations
**backfilled data**; these are destructive schema moves with no backfill (see
[`data-model.md`](data-model.md#migrations)), so there is no data transformation to assert.

## Running

`bash scripts/verify.sh` (add `--filter BookTest` for one). Tests run in parallel with one
in-memory SQLite DB per process — no shared state, and never against the dev database.
