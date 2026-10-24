# 01 — Act scope

## Scope

- Add `actId` to `SearchScope`, including `toQuery()` and `isNarrowed()`.
- `SearchRequest` gets `act`: nullable, exists in the chosen book, and it needs a book. An act together with `from_chapter`/`to_chapter` is an error: "Choose an act or a chapter range, not both."
- `SearchScopeFactory` resolves an act to its chapter IDs in story order and fills `chapterIds`. `ProjectSearch` then needs no change. Acts filter on `acts.id` in `scopedActQuery()`.
- `x-search.narrow-panel` gets an act select, which shows when a book is resolved. `SearchScopeSummary` names the act.
- Deferred to 03: any use on the find and replace page.

## Depends on

- None.

## Key decisions already made

- Act and chapter range cannot be used together. There is no overlap logic.
- The act select uses the same "book resolved" rule as the chapter picker (`SearchController::resolvedBook()`).

## Docs

- `expanded/open-questions.md` → 9.
- `expanded/architecture.md` → Facts from the code.

## Tests

- Extend `SearchScopeFactoryTest`, `SearchScopeSummaryTest` and `SearchTest`.
- An act scope limits scenes and chapters to that act. Acts limit to that act.
- An act from another book or another project gives a validation error.
- An act with a chapter range gives a validation error.
- An act with no book gives a validation error.
