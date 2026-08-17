# 05 — `RouteContext` and the authorization walks

Grow every ownership walk by one level, and replace the route→project resolver. No route URL
changes yet.

**Depends on:** 03.

## Scope

- `App\Support\RouteProject` → `App\Support\RouteContext`, resolving **both** the project and the
  book in one walk (`RouteContext::resolve(Request): self`, readonly `?Project $project`,
  `?Book $book`). `{act}` → `$act->book`; `{chapter}` → `$chapter->act->book`; `{scene}` →
  `$scene->chapter->act->book`. `{project}`, `{plotline}`, `{event}`, `{codexEntry}`,
  `{codexAttribute}` resolve a project and no book.
- Update its two callers: `ProjectNavigation` and the tracking middleware.
- Every `authorize()` walk grows a level: `$act->book->project`,
  `$chapter->act->book->project`, `$scene->chapter->act->book->project` — in `ActController`,
  `ChapterController`, `SceneController`, `StoryController`, `SceneShareController`,
  `FieldAutosaveController`, `RevisionController`, and the matching Form Requests.
- `revisionProject()` on `Act` / `Chapter` / `Scene` walks through the book.
- Eager-load `book` (or `act.book`) wherever the walk runs in a loop.

**Not in scope:** the `last_book_id` write (task 09), route re-nesting (task 06),
`ProjectNavigation`'s new book properties (task 09).

## Key decisions

- One walk, not two resolvers: resolving the book *is* the walk that resolves the project, and
  two copies drift the first time a route parameter is added.
- `Book` gets **no policy**. Authorization flows from the project, as it does for every other
  child.

## Consult

`expanded/architecture.md` → *Authorization*, *Route context*.

## Tests

- Existing 403 tests keep passing — they are the guard that no walk was dropped.
- A `RouteContext` unit test: each bound parameter resolves the expected project and book, and
  a project-only route yields a null book.
- An N+1 guard on whichever index page loops the walk.
