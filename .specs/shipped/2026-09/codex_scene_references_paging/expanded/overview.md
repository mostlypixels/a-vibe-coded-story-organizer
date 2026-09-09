# Overview

## What the source spec gets wrong about the code

Read before designing — three of its anchors have moved since it was drafted.

| Spec says | Actually |
|---|---|
| `CodexEntryController::referencingScenesInTimelineOrder()` | Extracted to `App\Services\ReferencingScenes::forEntry()`. The stale comment it asks to fix is gone. |
| Three call sites | Four. `scenes/show.blade.php` also lists `$referencedEntries`. |
| "Full page paginated at 20 rows", `config/search.php` `per_page` | `search.per_page` was deleted. Every paginated screen now uses `App\Support\PageSize` (the reader's own preference). Follow that. |
| Codex read page has no cap | It has a bad one: `codex/show.blade.php` hardcodes 20 and hides the rest with Alpine `x-show`. Every row is still in the DOM, so it caps nothing that costs anything. |

The `book.position` ordering defect is real and unfixed: `ReferencingScenes::forEntry()`
sorts on `(act.position, chapter.position, position)` with no book term, so book 2 act 1
sorts above book 1 act 2.

## Goals

- Four cards capped at `config('search.cap')` rows, with a `See all :count` footer link.
- Two full pages, paginated at `PageSize::resolve()`, in the `search/domain.blade.php` shape.
- Delete the Alpine show-all hack on `codex/show.blade.php`.
- Add `book.position` to the codex→scenes sort key.
- A scene row names its book, through `Book::displayName()`, only when the project has
  more than one book.

## Non-goals

`SceneReferenceMatcher`, the resync command, the pivot, AJAX expand-in-place.

## Acceptance criteria

- An entry referenced by 60 scenes renders 5 rows and a link reading *See all 60 results*.
- The full page honours the reader's rows-per-page and its `page=` survives a reload.
- A scene from book 1 act 2 sorts above one from book 2 act 1, on card and page.
- A non-owner gets 403 on both new routes.
- A single-book project prints no book name anywhere in these lists.
