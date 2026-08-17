# 06 — Re-nest the story routes under `{book}`

**Depends on:** 04, 05.

## Scope

- `projects.story.home` / `.overview` / `.overview.mode` → `books.story.*`.
- `projects.acts|chapters|scenes .index|create|store` → `books.*`. Shallow children
  (`/acts/{act}/edit`, `/scenes/{scene}/move-up`, …) are **unchanged**.
- The four controllers' nested actions take `Book $book` instead of `Project $project`.
- `overview_render_mode` now reads and writes on the **book**.
- Every `route('projects.acts.index', …)` and sibling call in the views moves to the book form.
- Story-page footers and totals become book totals.
- `x-chapter-pager` walks the current book's chapters.

**Not in scope:** the nav menus and breadcrumbs (task 09) — they keep pointing at the project's
first book until then, which still resolves. `BookController` is task 07.

## Key decisions

- Shallow nesting is preserved exactly: nested index/create/store, flat edit/update/destroy.
- Project-scoped routes stay put — plotlines, events, the whole codex, search, revisions,
  progress, and all of `admin.*`.

## Consult

`expanded/architecture.md` → *Routing*; `expanded/ui.md` → *Story pages*.

## Tests

- `ActTest` / `ChapterTest` / `SceneTest` / `StoryTest` re-pointed at the book routes, with
  their 403 cases intact.
- A book's story pages show only that book's acts, chapters and scenes.
- The overview mode round-trips on the book, and `?chapter=` from another book is a 403.
