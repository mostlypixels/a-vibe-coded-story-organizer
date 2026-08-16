# 11 — `PublicationSetting` moves onto the book

**Depends on:** 07.

## Scope

- `PublicationSetting` belongs to a `Book`; the `book_id` unique index replaces `project_id`
  (the migration itself landed in task 03 — wire the model, controller and form here).
- `Project::publicationSettingOrDefault()` → `Book::publicationSettingOrDefault()`, returning
  the same unsaved default instance when no row exists.
- Rename `include_project_cover` → `include_book_cover`, column and label ("Include book
  cover").
- `PublicationSettingController` and `UpdatePublicationSettingRequest` bind `{book}` and
  authorize through `$book->project`.
- The Export-ebook config page loads the selected **book's** row.

**Not in scope:** the EPUB export itself and the book picker on that page — task 12.

## Key decisions

- No auto-creation. A book that never visited the config form keeps the lazy default, exactly as
  a project did.
- The section-order reorder routes move to `{book}` with the rest.

## Consult

`expanded/data-model.md` → *Changed foreign keys*; `expanded/export-import.md` → *EPUB*.

## Tests

- `PublicationSettingTest`: settings save per book; two books in one project hold independent
  configs; a non-owner gets 403 on update and on both section-order routes.
- The lazy default is returned for a book with no row.
