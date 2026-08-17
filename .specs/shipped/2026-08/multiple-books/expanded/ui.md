# UI

## How visible is the book layer

Almost every existing project holds one book, and the feature must not tax it. One predicate
decides, everywhere: **`Book::hasOwnName()`** (`books.name !== null`).

| | Sole book, no name of its own | Book with its own name |
|---|---|---|
| Picker trigger | `Melusine` — one line | `Volume One` over `Melusine` |
| Breadcrumb | `Dashboard › Story › Acts` | `Dashboard › Volume One › Story › Acts` |
| Page title | `Melusine - <app>` | `Volume One - <app>` |

Derived from the **name**, never from a book count: a writer who deliberately names a sole book
still sees the layer, and no page pays for a `count()` query. After the second-book freeze hook
(see [`data-model.md`](data-model.md#the-name-fallback)) a multi-book project has no unnamed
books, so "has its own name" and "there is more than one book" agree in practice.

## The picker becomes two levels

`layouts/navigation.blade.php`'s left block is the only control that switches context, so it
carries the book.

- **Trigger** names the book via `displayName()`. It adds the project beneath it in smaller
  muted text **only when `Book::hasOwnName()`** — see *How visible is the book layer* below.
- **Panel**: the current project's books first (the open one marked active — unlike today's
  project list, where the open project is omitted, a book list with a hole reads as broken),
  then a divider, then each other project as an unlinked `x-navigation.section-heading` with
  its books indented beneath, then `All projects →`.
- **Caps**: keep `PICKER_PROJECT_LIMIT = 5` for other projects and cap **their** books at 5
  too; the current project lists all of its books. `otherProjects()` must
  `->with('books')` — two menus render this, and the memoization already there is what stops
  it doubling.
- The responsive menu mirrors it as flat rows with the same order, per the existing
  "the menus are markup only" rule.
- Add `Manage books →` at the foot of the current project's group, linking
  `projects.books.index`.

## Books index

`resources/views/books/{index,create,edit}.blade.php`, modelled on `acts/` — the closest
sibling (ordered, position-scoped, delete-with-move). Reuse `x-table`, `x-icon-move-button`,
`x-create-actions`, `x-edit-actions`, `x-delete-with-move-dialog`.

- Columns: `#` (position), name, acts count, word count, move up/down, edit, delete.
- Delete reuses the act pattern exactly: the dialog counts acts + chapters + scenes about to
  cascade and offers "move my acts to <other book>" via `ReparentsChildren`
  (`reparentChildren($book, $destination, 'acts', 'book')`) inside one transaction.
- The **last** book renders no delete control at all, and the route 403s anyway — the same
  belt-and-braces the main plotline gets.
- There is no empty state: a project always has a book.

## Book home (`books.show`)

The landing the picker and the breadcrumb point at. Deliberately thin, and deliberately not a
second dashboard: book word count against nothing (goals are project-level), recent scenes in
this book (`RecentlyEdited::scenes($book)`), and links to Overview / Acts / Chapters / Scenes.
`projects.show` keeps the progress chart, the goals, the recent codex list, and gains a compact
book list. See [Q6](open-questions.md) if this page should be dropped for the overview instead.

## Breadcrumbs

`App\Support\Breadcrumbs` gains a book crumb on book-scoped pages only:

```
Dashboard › <Book> › Story › Acts › Edit act 3     (book-scoped)
Dashboard › Codex › Characters                      (project-scoped, unchanged)
```

The book crumb appears only when `hasOwnName()`, links `books.show`, and is the current leaf on
`books.show` itself. The Story crumb keeps linking its stub, now `books.story.home`.
`storyTrail()` takes the book instead of the project; `timelineTrail`/`codexTrail`/`toolsTrail`
are unchanged.

## Page title

`App\Support\PageTitle` reads the route's **book** when there is one, through `displayName()`,
else the route's project: `"<book> - <app>"` / `"<project> - <app>"`. A sole unnamed book
therefore renders exactly today's title. The existing rule survives intact — the title follows
the URL, never the account's stored context, and the leading name is what a truncated tab still
shows. Two books with the same name in different projects become indistinguishable in the tab
strip; that is the accepted cost of not putting both names in front of the truncation.

## Story pages

- Overview, act/chapter/scene indexes and edit pages take `{book}`; their footers' totals
  become book totals; the `#` column shows book-wide numbers.
- The overview's mode toggle writes `books.overview_render_mode`; its label changes with
  `StoryOverviewMode::Whole` ("Whole book" reads correctly for the first time).
- `x-chapter-pager` walks the current **book's** chapters, not the project's.
- Act edit gains nothing: moving an act between books happens in the book delete dialog. A
  standalone "move this act to another book" control is a follow-up, not this spec.

## Export & import screens

- **Export ebook** (`admin/data/export-ebook.blade.php`): the project `<select>` becomes a
  book `<select>` grouped by `<optgroup label="<project>">`. It posts `book_id`; the page's
  `PublicationSetting` form loads that **book's** row (or the unsaved default). Rename the
  `include_project_cover` toggle's label to "Include book cover".
- **Export project** (`.zip`) stays a project picker — the archive holds every book.
- The import page is unchanged.
