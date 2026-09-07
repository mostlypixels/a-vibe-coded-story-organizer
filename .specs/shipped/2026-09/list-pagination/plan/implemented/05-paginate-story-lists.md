# 05 — Paginate the story lists

## Scope

- Paginate `SceneController::index`, `ChapterController::index`,
  `ActController::index`, `BookController::index`.
- The bar in all four index views.
- Fix the move up/down buttons in those four views:
  `:disabled="$loop->first && $acts->onFirstPage()"` and
  `:disabled="$loop->last && $acts->onLastPage()"`.

## Depends on

03.

## Not in scope

- `$loop->first` is **already** wrong under a name sort or a descending
  direction, where the visually first row is not the positionally first sibling.
  Pre-existing, not made worse by pagination, not fixed here.
- The scene list's 400-option chapter filter. Its own feature.

## Key decisions

- **Story numbers stay book-wide.** `StoryNumbering::forBook($book)` is built
  from the whole book, never from the paginated set. Page 4 of Scenes must read
  301-400.
- **Full sets stay full**: `chaptersFor($book)`, `actsFor($book)`,
  `$destinationActs` / `$destinationChapters` / `$destinationBooks`, and the
  `DuplicateName` `$names` pluck. Moving and filtering are not limited to the
  visible page.
- `SceneController`: `->paginate()` goes after the whole `orderBy` chain. The
  `select('scenes.*')` and both joins stay exactly as they are — read the
  existing comment before touching them.
- `ChapterController` and `ActController`: nothing may `select()` after the
  `withCount` / `withSum` aliases. Put `->paginate()` at the end.
- `BookController`: `$wordCounts` keys off `$books->pluck('id')`, which is now
  the page's ids. That is the intent, not a bug.
- **A row moved up from the top of page 2 lands on page 1 and leaves the view.**
  Accepted, not fixed. The button is honest — the row did move — and following it
  means computing its new page in four controllers for a rare case. Do not
  "fix" this without reopening the decision.

## Consult

- `expanded/architecture.md` → Index actions, Must stay unpaginated
- `expanded/ui.md` → Move buttons

## Tests

Extend `tests/Feature/ListPaginationTest.php`:

- Each of the four indexes hands a `LengthAwarePaginator` to its view.
- 120 scenes at the default size: page 1 has 100, page 2 has 20.
- 120 scenes, page 2: the first row's `#` is 101.
- Move buttons: page 2's first row is not disabled; page 1's is. Page 2's last
  row is not disabled when a page 3 exists.
- The scene `chapter` filter and the sort survive the page link.
- A non-owner gets 403 on `?page=2`.

Update any existing assertion in `SceneTest`, `ChapterTest`, `ActTest`,
`BookTest` that reads the view's collection as an Eloquent `Collection` or counts
rows in the response. Grep for `assertViewHas` on those keys first — the breakage
is small.
