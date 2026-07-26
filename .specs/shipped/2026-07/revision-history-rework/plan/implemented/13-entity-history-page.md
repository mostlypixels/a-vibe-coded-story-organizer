# Task 13 — The entity history page

## Scope

**Route** (`routes/web.php`, inside `auth`, keeping the `whereIn('entity', slugs())` gate):

* `GET /revisions/{entity}/{id}` → `revisions.index` → `RevisionController@index`;
* `GET /revisions/{entity}/{id}/{field}` → `revisions.field` → `RevisionController@field`,
  a redirect to `revisions.index` + `?field=`.

**Controller** — split `RevisionController::resolve()` into `resolveEntity(slug, id)`
(findOrFail + `authorize('view', revisionProject())`) and
`resolveFieldFilter(slug, request)` (null, or a registered field — unknown 404s through
`AutosavableFields::resolveField()`, keeping that contract in one place). `index` then
just calls `RevisionHistory::forEntity()` and returns the view. No query logic here.

**View** — rewrite `resources/views/revisions/index.blade.php` inside the existing
`<x-revisions-layout>`, plus a new partial `revisions/partials/save-point.blade.php`:

* controls row: **field filter** (native `<select>`, GET, listing only fields that have
  revisions, plus *All fields*), the existing **label search**, and a new
  **manual saves only** checkbox — all three GET parameters, all three preserved in the
  paginator links;
* a `<ul>` of save-point rows (not `x-table`: a save point is two-level and a table row
  cannot hold that) — header line (date, author, `x-revision-origin-badge`, label,
  **Current** badge), then one line per field with its `summary_html` through
  `<x-diff inline>` and, when `change_count > 1`, "and N−1 more changes" linking to
  `revisions.compare` with the pair prefilled;
* *Compare with previous* per row (using the boundary group for the last row of a page);
* baseline save points keep the italic "Initial value — before revision history" line;
* empty state and paginator links.

The **Undo this save** button is task 17 — leave the slot in the partial with a comment.

Delete the now-unreachable field-scoped history view code paths, and rewrite
`tests/Feature/RevisionHistoryTest.php` for the new routes.

## Depends on

Tasks 10, 12.

## Key decisions already made

* The field-scoped page becomes a **filter plus a redirect** — one concept, one page.
  Binding decision 1.
* The manual-only filter belongs on the page, not only in the pickers (grill decision 11).
* The list never selects `value`; summaries are the stored columns, never computed here.
* `?field=`, `?label=`, `?manual=`, `?page=` are the whole state — the page is a pure GET.

## Consult

* `expanded/ui.md` — *1. History page*, including the row sketch.
* `expanded/architecture.md` — *Routes*, `@index`.
* `resources/views/revisions/index.blade.php` — what is being replaced (keep the header /
  "Back to editing" pattern and the `x-revisions-layout` usage).

## Tests

Rewrite `tests/Feature/RevisionHistoryTest.php`:

* owner sees the entity history; **non-owner gets 403**; guest is redirected to login;
* one row per save point, newest first, naming every field the save touched;
* `?field=` filters; an unregistered `?field=` 404s; `?label=` filters;
  `?manual=1` shows only manual save points; the three combine;
* summaries render, escaped, with "and N more changes" linking to compare with `from`/`to`;
* pagination at the configured per-page; the last row of page 1 and the first row of
  page 2 both get a working *Compare with previous* link;
* the current save point carries the **Current** badge; the baseline row renders
  "Initial value";
* the legacy `/revisions/{entity}/{id}/{field}` URL redirects to `revisions.index?field=…`;
* a query listener asserts no select against `revisions` includes `value`.
