# Scoped search — plan overview

The manual for this feature's tasks. Never implemented, never moved.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 1 | `01-story-ordered-chapters.md` | `Book::chaptersInStoryOrder()` and `StoryNumbering::fromChapters()`. Shared by the scene forms and the range picker. |
| 2 | `02-search-scope.md` | The `SearchScope` value object and the factory that resolves a chapter range. |
| 3 | `03-search-request-validation.md` | Book, chapter and domain parameters, cross-checked against the project. |
| 4 | `04-project-search-filters.md` | `ProjectSearch` applies the scope: book, range, skipped domains, codex types. |
| 5 | `05-controller-wiring.md` | Both actions build the scope; filters ride every link and the paginator. |
| 6 | `06-narrow-panel.md` | The book, range and domain controls inside the existing GET form. |
| 6b | `06b-search-sections.md` | One definition of the Timeline/Story/Codex grouping, read by the panel, the results and task 07. |
| 7 | `07-hidden-sections-and-summary.md` | Sections a book filter makes meaningless, the explanatory line, the filter summary. |

Service before controller before view: each layer's tests need the one below it.

## Binding decisions

Settled in the grill. Do not re-litigate.

- **Filter on the Builder, never after.** `ProjectSearch::searchEntity()` hydrates rows and
  matches in PHP. A filter applied after matching has already paid for `Scene.contents`.
  Every filter belongs in `queryFor()`, or in the decision to skip a domain.
- **`SearchScope` is pure.** It holds a resolved chapter id list and runs no query. A
  separate factory does the lookup and the reversed-range swap, so the object unit-tests.
- **Two methods, not one.** `includes()` answers "does this domain run". `hiddenByBook()`
  answers "is it absent because the book filter makes it meaningless" — only that one
  prints the explanatory line. An unchecked domain is silent.
- **Codex types filter in SQL.** One codex query, `whereIn('type', …)` for the included
  types. Never a PHP split after hydration.
- **The domain page ignores `domains[]`.** The URL names the domain; a stale checkbox does
  not override it. It redirects only when `hiddenByBook()` is true.
- **A range needs a book**, because numbering restarts per book. The control exists only
  when a book is resolved: the chosen one, or the only one.
- **Empty `domains` means all**, so a bare search has a clean URL.
- **Chapter options load only when a book is resolved.** A multi-book project's landing
  page runs no chapter query.
- **`x-collapsible-card` is the disclosure.** It is already a `<details>`, so it works
  inside the GET form. No third pattern.

## Invariants every task preserves

- **Story order is `(acts.position, acts.id, chapters.position, chapters.id)`.** Never
  `position` alone — it is per-parent and gappy — and never `name`.
- **One query-string builder.** `SearchScope::toQuery()` feeds the "see all" link, the
  domain page's links, and `$paginator->appends()`. Never a hand-built filter string.
- **The default scope changes nothing.** `new SearchScope()` leaves every existing caller,
  view and test behaving as it does today.
- **Book naming.** Print `Book::displayName()`. Never `->name`, never `#<id>`.
- **Cross-project ids fail validation**, they are not silently ignored.
- **Matching is untouched.** No change to accent folding, `SearchMode`, or `SearchSnippet`.
