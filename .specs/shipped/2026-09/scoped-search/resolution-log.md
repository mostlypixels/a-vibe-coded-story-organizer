# Scoped search — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- All three filters ship together. A book filter alone still leaves a serial writer with
  half a cliff.
- Story-ordered chapters already existed as a private `SceneController` method, so this
  feature does not wait on `list-jump-to-position`. Open question 6 is closed.
- `SearchScope` stays a pure value object and a factory resolves the chapter range. The
  spec asked for both `fromRequest($request, $project)` and a unit test, which cannot both
  be true.
- `includes()` split into `includes()` and `hiddenByBook()`. The view prints the
  explanatory line only for the second, so the two reasons a domain is absent must stay
  apart.
- Codex types filter in SQL, not by a PHP split after hydration.
- The domain page ignores `domains[]` and redirects only on `hiddenByBook()`. The URL
  naming a domain is a clearer statement of intent than a checkbox in the same URL.
- Chapter numbers come from a new `StoryNumbering::fromChapters()`, so the picker does not
  load every scene in the book through `forBook()`.
- Chapter options load only when a book is resolved.
- The whole-book range short-circuits instead of building a `whereIn` over every chapter.
  No 1000-chapter fixture: SQLite allows 32766 bound variables, and the test would cost
  seconds on every suite run to guard a ceiling 32x away. Open question 4 is closed.
- `x-collapsible-card` is already a `<details>`, so it nests in the GET form. No third
  disclosure pattern.

## Deviations from the spec/plan

- Task 06b inserted after 06. The panel wrote the Timeline/Story/Codex grouping a second
  time and task 07 needed it a third, so the grouping moves to a `SearchSection` enum
  before 07 reads it.

- Task 04 asked for a test that a book filter leaves Plotlines, Events and codex rows
  "untouched". It cannot: `includes()` is `selects() && ! hiddenByBook()`, so a book
  filter skips those domains and their queries never run. The test asserts the skip
  (query count 4: acts, chapters, scenes, `booksById()`) and a second test proves a
  chapter range alone, with no book, leaves them full.
- Codex no longer builds its query in `queryFor()`. One `searchCodex()` builds it with
  `whereIn('type', …)`; `queryFor()`'s codex arm throws, so no second definition can
  drift. `searchDomain()` for a codex domain filters the type in SQL instead of
  splitting rows after hydration.

## Issues → resolutions

- The filter summary read "Filtered to 1 domains" and built itself in a `@php` block in
  `search/index.blade.php` — the presentation logic task 06b had just removed from the
  panel. `App\Support\SearchScopeSummary` now builds the parts: it names the domains one
  by one up to three, and counts them with a plural rule beyond that. Found by driving the
  page in a browser; no test caught it, because every test asserted the summary existed
  rather than what it said.
