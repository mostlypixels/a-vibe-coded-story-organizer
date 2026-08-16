# 07 — `BookController` and the books index

**Depends on:** 06.

## Scope

- `Route::resource('projects.books')->only([index, create, store, show, edit, update, destroy])->shallow()`
  plus `books.move-up` / `books.move-down`. `show` is task 08's page; register the route here.
- `BookController`, `StoreBookRequest`, `UpdateBookRequest`, `DestroyBookRequest`.
- `resources/views/books/{index,create,edit}.blade.php`, modelled on `acts/` — the closest
  sibling. Reuse `x-table`, `x-icon-move-button`, `x-create-actions`, `x-edit-actions`,
  `x-delete-with-move-dialog`.
- The edit form carries the moved metadata: name, description, cover, language, author,
  publisher, rights, ISBN, and the four matter fields.
- Delete-with-move: reassign this book's acts to another book via
  `reparentChildren($book, $destination, 'acts', 'book')`, inside one transaction.
- `ProjectDeleteWarning`: add the books category.
- A compact book list on `projects.show`.

**Not in scope:** `books.show`'s page body (task 08), the picker (task 09).

## Key decisions

- **Name is optional on the sole book, required from the second onward** — `Rule::requiredIf`
  on whether the project already has a book. Optional so the writer can edit the sole book's
  ISBN without a forced rename.
- **The last book cannot be deleted.** The route 403s, *and* the index renders no delete control
  for it — the same belt-and-braces the main plotline gets.
- **The delete warning shows the true book count and hides the category at one book.** Do not
  subtract the auto-created book the way the plotline and event categories do: deleting a
  three-book project loses three books.
- No empty state on the index — a project always has a book.

## Consult

`expanded/ui.md` → *Books index*; `expanded/data-model.md` → *Model invariants*, *The name
fallback*; `expanded/architecture.md` → *Other project-level services*.

## Tests

`BookTest`, second slice:

- CRUD happy path; non-owner 403 on every action including both move routes.
- Missing name on a second book → `assertSessionHasErrors('name')`; an empty name on the sole
  book saves fine.
- `move-up` / `move-down` swap adjacent positions and no-op at the ends.
- Delete-with-move: acts append to the destination after its existing acts, relative order
  preserved, no position collision. Without a destination, the subtree cascades.
- Deleting the last book is a 403 and the book survives.
- `ProjectTest`: the delete warning counts books truthfully and hides the category at one.
