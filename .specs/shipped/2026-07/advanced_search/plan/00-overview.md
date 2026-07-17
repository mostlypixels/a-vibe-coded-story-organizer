# Advanced search — plan overview

Never implement or move this file. It is the manual for the numbered task files in this
folder; `plan-implementer` reads it for context but only ever touches `NN-*.md`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-search-mode-and-snippet.md` | `SearchMode` enum + `SearchSnippet` highlighting/escaping helper. No dependencies. |
| 02 | `02-project-search-service.md` | `ProjectSearch` service: the six per-entity queries, 3 modes, LIKE-wildcard escaping, field-level match rows. Depends on 01. |
| 03 | `03-search-route-controller.md` | Route, `SearchController`, `SearchRequest`, authorization. Depends on 02. |
| 04 | `04-search-results-view.md` | `search/index.blade.php` + `x-search-result-table` component: form, 3-section/3-column grid, snippets, empty states, accessibility. Depends on 03. |
| 05 | `05-nav-integration.md` | Add "Search" to the desktop + responsive nav, last item, with active-state highlighting. Depends on 03 (route must exist to link to). |

04 and 05 both depend only on 03 and are independent of each other — either order (or
parallel `plan-implementer` runs) is fine.

## Binding decisions (do not re-litigate)

These were settled in the `grilling` pass over `expanded/*.md` and are binding on every task:

* **Six searchable entities/fields, exactly as scoped in `overview.md`** — `Act`
  (name, description), `Chapter` (name, description), `Scene` (name, description, contents,
  notes), `Event` (title, description), `Plotline` (name, description), `CodexEntry`
  (name, description). **`CodexAlias.alias` and `CodexAttributeValue.value` are out of scope**
  for this feature — do not add them.
* **Sections**: Timeline (Plotlines, Events), Story (Acts, Chapters, Scenes), Codex
  (Characters, Locations, Organizations — one column per `CodexEntryType`, not one combined
  Codex column).
* **Columns**: every section renders as a 3-column grid. Story and Codex fill all 3; Timeline
  fills 2 and leaves the 3rd empty — do not collapse Timeline to a 2-column layout.
* **Multi-field matches**: one result row per matching field, not one row per entity. A Scene
  matching in both `contents` and `notes` produces two rows.
* **AND mode**: a term is satisfied if it appears *anywhere* across the entity's searchable
  fields — terms are **not** required to all appear in the same field. (E.g. "dragon castle"
  in AND mode matches a Scene with "dragon" in `contents` and "castle" in `notes`.)
* **OR mode**: any term matches, in any field.
* **Exact-phrase mode**: the whole query string matched verbatim (case-insensitive) in a
  single field — no term-splitting.
* **Default mode on first page load**: AND (`SearchMode::AllTerms`).
* **Result ordering within a column**: each entity's existing natural order — `position` for
  Act/Chapter/Scene, `event_datetime` for Event (matching `EventController@index`'s default),
  `name`/creation order for Plotline and CodexEntry. No relevance scoring.
* **LIKE wildcard escaping**: user-supplied terms must have `%` and `_` escaped before being
  interpolated into a `LIKE` pattern (with a matching `ESCAPE` clause), so a literal `%`/`_`
  in a search term is not treated as a SQL wildcard. This is a correctness requirement, not
  optional polish.
* **Search runs against raw stored values**, not rendered output — `Scene.contents` (Markdown
  source) and `Scene.notes` (sanitized rich HTML source), never `Scene::renderedContents`.
* **Snippet highlighting**: `<mark>` around matched terms, ~120 characters of context centered
  on the first match in that field, `bg-sun-200` (`#ffe494`, a light highlighter yellow) for
  styling. (The `sun` palette in `tailwind.config.js` originally skipped `200`; it was filled in
  — along with the other palette gaps — during the pre-ship review, see `resolution-log.md`.)
  The snippet HTML is built once,
  centrally, in `SearchSnippet` (escape-then-highlight) — the view renders it with `{!! !!}`
  in exactly one place; entity names and field labels stay auto-escaped `{{ }}`.
* **No AJAX** — plain `GET` form, full-page reload, `q`/`mode` round-trip via query string.
* **Empty query** (`q` absent/blank) is the normal landing state: render the form with no
  results section, never a validation error.
* **No new package, no new migration, no new index** — see `architecture.md` /
  `data-model.md` for why (multi-driver DB support rules out FULLTEXT/FTS5; `LIKE` scans are
  fine at this project's scale).
* **Not in this feature** (tracked in the separate `search_pagination` draft spec): capping
  result counts per column and a paginated "see all results for one domain" page. Do not add
  pagination or result-count limiting here — that is explicitly a follow-up feature that
  depends on this one shipping first.

## Core invariants every task must preserve

* **Authorization flows from the Project** (`CLAUDE.md` § Authorization /
  `documentation/architecture.md`): every search request authorizes via
  `$this->authorize('view', $project)` in the controller, mirrored in `SearchRequest::authorize()`
  as `$this->user()->can('view', $this->route('project'))`. A non-owner must get a 403 —
  cover this with a test (task 03).
* **Escape output unless intentionally rendering trusted HTML** (`CLAUDE.md` § Security): the
  one deliberate `{!! !!}` in this feature is the pre-escaped, pre-highlighted snippet HTML
  from `SearchSnippet` — nothing else in the search view uses `{!! !!}`.
* **No N+1 queries**: the six entity types are fetched with a small, fixed number of queries
  regardless of how many rows match (task 02's service is the single place this is enforced;
  task 03/04 must not add per-row queries, e.g. no N+1 from resolving edit URLs).
* **Cross-project isolation**: every query is scoped to the authorized `$project` (directly by
  `project_id`, or via the `chapter.act.project_id` / `act.project_id` parent chain for
  `Scene`/`Chapter`) — an entity belonging to another project must never appear in results.
