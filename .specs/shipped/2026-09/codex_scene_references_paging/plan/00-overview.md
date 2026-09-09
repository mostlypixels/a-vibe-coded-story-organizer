# Codex ↔ scene references: capped lists with a "see all" page — plan overview

The manual for this feature's tasks. Never implemented, never moved.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 1 | `01-book-position-ordering.md` | Add `book.position` to the codex→scenes sort key. Failing-first regression test. |
| 2 | `02-referencing-scenes-for-scene.md` | Add `ReferencingScenes::forScene()`; dedupe three inline copies; drop the dead `cover` eager load. |
| 3 | `03-codex-scenes-page.md` | `codex.scenes.index` route, action, hand-built paginator, `scene-row` component, full page. |
| 4 | `04-codex-cards-capped.md` | Cap both codex cards at `config('search.cap')`; delete the Alpine show-all hack. |
| 5 | `05-scene-entries-page.md` | `scenes.codex-references.index` route, action, real `->paginate()`, `entry-row`, full page. |
| 6 | `06-scene-cards-capped.md` | Cap the shared partial; `scenes/show` adopts it; AJAX fragment keeps the cap. |

Route before card, in both directions: a capped card links to a page that must already exist.

## Binding decisions

Settled in the grill. Do not re-litigate.

- **One scene row shape.** Both codex cards and the codex full page render the same
  `scene-row`: scene, chapter, act, book (conditional), event. Every scene link targets
  `scenes.show`. The `codex/edit` card loses its `<ul>` and its `scenes.edit` link.
- **One entry row shape.** Name + type. No cover thumbnail; the `->with('cover')` eager
  load on the scene side is dead and gets deleted.
- **`config('search.cap')` is the only inline cap.** No `config/references.php`.
- **Full pages paginate by `PageSize::resolve($request->user()?->page_size)`.** There is no
  `search.per_page`.
- **The two directions page differently, on purpose.** Codex→scenes sorts in PHP, so it
  builds a `LengthAwarePaginator` by hand over `forPage()`, copying
  `SearchController::domain()` including `Paginator::resolveCurrentPath()`. Scene→entries
  orders in SQL, so it calls `->paginate()` on the relation. Each action carries a comment
  saying why, or the next reader "fixes" one to match the other.
- **Back links are fixed to the read page** — `codex.show` and `scenes.show`. Never
  `url()->previous()`, never a `?from=` param.
- **Book column appears when `$project->books()->count() > 1`**, resolved once in the
  controller and passed down as `$showBook`. Never derived per row.
- **Timeline order only** on the codex full page. No sortable columns.

## Invariants every task preserves

- **Sort key.** Unassigned scenes last; then `(event_datetime, event id)`; then
  `(book.position, act.position, chapter.position, scene.position)`. Card and full page
  show the same order, and the order survives a page boundary.
- **Authorization.** Every new action calls `authorize('view', $model->project)`, walking
  to the owning `Project` via `ProjectPolicy`. A non-owner gets 403.
- **Book naming.** Print `Book::displayName()`. Never `->name`, never `#<id>`.
- **Footer link wording.** `See all :count results`, matching
  `components/search/result-table.blade.php` word for word. No link when the count is at
  or below the cap.
- **No change** to `SceneReferenceMatcher`, the resync command, the pivot, or when it is
  recomputed.
