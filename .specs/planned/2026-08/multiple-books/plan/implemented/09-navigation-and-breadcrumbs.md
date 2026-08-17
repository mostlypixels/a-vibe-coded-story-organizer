# 09 — Picker, breadcrumbs, page title

**Depends on:** 08.

## Scope

- `ProjectNavigation` gains `?Book $routeBook`, `?Book $book`, `booksActive`. The off-book
  fallback is `$project->lastBook ?? $project->books()->first()`.
- The tracking middleware writes `projects.last_book_id` when the route resolves a book,
  alongside `users.active_project_id`. Keep the existing post-`$next()`, 2xx-only gate — that
  ordering **is** the authorization check.
- The picker becomes two levels in both menus: current project's books first (open one marked
  active), then other projects with their books, then `All projects →`. Add `Manage books →`
  under the current project's group.
- Breadcrumbs: a book crumb on book-scoped pages, linking `books.show`. `storyTrail()` takes the
  book. Timeline / codex / tools trails are unchanged.
- `PageTitle` reads the route's book through `displayName()`, else the route's project.
- The Story dropdown's links build from `$navigation->book`.

**Not in scope:** nothing deferred — this closes the navigation.

## Key decisions

- **`Book::hasOwnName()` is the single predicate** for how visible the layer is. A sole unnamed
  book shows one picker line, no book crumb, and today's page title. Derived from the name,
  never from a count, so no page pays for a `count()` query.
- The open **book** stays listed in the panel, marked active — unlike the open *project*, which
  the trigger already names. A book list with a hole reads as broken.
- Caps: other projects stay capped at five, and their books at five. The current project lists
  all its books. `All projects →` remains the complete answer.
- `last_book_id` is not fillable; only this middleware writes it.
- Guard the menus on the **book**, not on `hasProject()` — a guest or error render has a project
  but no book.

## Consult

`expanded/ui.md` → *How visible is the book layer*, *The picker becomes two levels*,
*Breadcrumbs*, *Page title*; `expanded/architecture.md` → *Route context*.

## Tests

- `NavigationTest`: two levels render; the open book is active; a sole unnamed book shows one
  line. **A query-count guard** — both menus render the picker, so `otherProjects()` must
  eager-load books and stay memoized.
- `BreadcrumbsTest`: book crumb present on story pages when named, absent when not, absent on
  codex / timeline / tools.
- `PageTitleTest`: book pages by book, project pages by project, off-route the bare app name. A
  sole unnamed book renders exactly today's title.
- `ActiveProjectTest`: `last_book_id` is written on a 2xx book page, never on a 403, and a page
  with no book leaves it alone.
