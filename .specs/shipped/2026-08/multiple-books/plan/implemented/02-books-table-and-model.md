# 02 — The `books` table and the `Book` model

Introduce the model and its invariants. Acts still hang off the project at the end of this
task; nothing user-visible changes. This task also carries the prep that keeps task 03 purely
mechanical.

**Depends on:** 01.

## Scope

- `create_books_table` — columns in `expanded/data-model.md` → *`books` (new)*.
- `add_last_book_id_to_projects_table` — a **separate** migration, because `books.project_id`
  already points the other way. Nullable, `nullOnDelete`, **not fillable**.
- `Book` model: `HasFactory`, `HasRevisions`, `HasSiblingPosition` (`siblingScopeColumn()` =
  `project_id`), `SanitizesRichHtml`, `revisionProject()`.
- `Book::displayName()` and `Book::hasOwnName()` — the two readers of the nullable name.
- `Book::created` hook: copy the project's current name onto every **unnamed sibling**, so the
  first book stops tracking the project the moment a second exists.
- `Book::deleting` / `Book::deleted` hooks — cover purge and the word-count snapshot.
- `Project::books()`, `Project::lastBook()`; `Project::created` hook also creates the first
  book, **unnamed**, beside the main plotline and the bookends.
- `BookFactory`.
- Seeders create their book explicitly (positions by hand — `WithoutModelEvents`), but acts
  still attach to the project.
- `AutosavableFields`: add the `'book'` slug and its six fields; strip the five moved fields
  from `'project'`. Re-key `config/revisions.php` `caps`/`windows`. Add `book` to
  `LongTextColumnsMigrationTest`'s own copy of the widened-column list.
- A `TestCase` helper that returns a project with its book, ready for task 03.

**Not in scope:** moving `acts` (task 03), moving the metadata columns off `projects` (task 03's
migration set), any route, controller or view (tasks 05–09).

## Key decisions

- The auto-created book has **no name**. It tracks the project's name through `displayName()`.
- The freeze hook is a model **invariant**, not controller workflow — "an unnamed book exists
  only while it is the project's only book". Suppressed under `WithoutModelEvents`, which is
  correct for seeders and the importer.
- `last_book_id` is written only by the tracking middleware (task 09), never mass-assigned.

## Consult

`expanded/data-model.md` → *`books` (new)*, *The name fallback*, *Model invariants*,
*Migrations*, *Factories & seeders*.

## Tests

New `tests/Feature/BookTest.php`, first slice:

- Creating a project creates exactly one book, with a null name.
- `displayName()` falls back to the project name; `hasOwnName()` is false.
- Creating a second book copies the project's name onto the first; a later project rename
  leaves both alone.
- `position` auto-assigns per project.
- Deleting a book purges its own cover and every chapter cover beneath it (`Storage::fake`).
- Deleting a book re-records the project's word-count snapshot.
