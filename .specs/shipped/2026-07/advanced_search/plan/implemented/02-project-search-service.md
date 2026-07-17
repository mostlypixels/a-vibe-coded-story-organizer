# Task 02 — ProjectSearch service

## Scope

Build `app/Services/ProjectSearch.php`: the single class that, given a `Project`, a raw query
string, and a `SearchMode`, runs the six per-entity queries and returns a grouped result
structure ready for the controller/view to consume — no HTTP concerns here.

* One query per entity type (`Act`, `Chapter`, `Scene`, `Event`, `Plotline`, `CodexEntry`),
  each scoped to the authorized project:
  * `Act`, `Event`, `Plotline`, `CodexEntry` — direct `project_id`.
  * `Chapter` — via `act.project_id`.
  * `Scene` — via `chapter.act.project_id`.
* **Term splitting**: whitespace-split the query into terms for AND/OR modes. Exact-phrase
  mode does not split — the whole string is one literal phrase.
* **AND mode**: each term must appear in *at least one* of the entity's searchable fields —
  terms are not required to share a field. Build this as one `where(fn ($q) => ...)` group per
  term, each group `orWhere`-ing across that entity's fields, all groups chained with `AND`
  (i.e. `->where(fn...)->where(fn...)` — one closure per term).
* **OR mode**: one `where(fn ($q) => ...)` group, every term `orWhere`-ed across every field.
* **Exact-phrase mode**: `LIKE '%<phrase>%'` (escaped, see below) `orWhere`-ed across the
  entity's fields, no term splitting.
* **LIKE-wildcard escaping**: before interpolating any term/phrase into a `LIKE` pattern,
  escape `%` and `_` (e.g. `addcslashes($term, '%_\\')`) and use the matching `ESCAPE '\\'`
  clause (or Laravel's built-in escaping if the installed version exposes one — check
  `Illuminate\Database\Query\Builder` for a `whereLike`/escaping helper before hand-rolling).
  A literal `%` or `_` typed by the user must match literally, not act as a wildcard.
* **Field-level result rows**: for each entity row returned by a query, determine in PHP
  (against the already-fetched row — no extra query) *which* of its fields actually matched,
  and emit **one result row per matching field** (per the binding decision in `00-overview.md`
  — a Scene matching in both `contents` and `notes` yields two rows). Each row carries: the
  entity (for building an edit URL later, task 03/04), a field label (e.g. "Contents",
  "Notes", "Name", "Description"), and a snippet built via `SearchSnippet` (task 01) using the
  raw field text.
* **Grouping**: return results grouped as Timeline (Plotlines, Events) / Story (Acts,
  Chapters, Scenes) / Codex (one bucket per `CodexEntryType`, from `CodexEntry->type`) — a
  small value object or nested array/collection is fine; match whatever shape task 04's view
  needs (coordinate the exact shape when starting task 04, but keep it simple — e.g. a
  `SearchResults` DTO with named properties per column, or a keyed collection).
* **Ordering**: within each entity's query, `orderBy` its natural order — `position` for
  Act/Chapter/Scene, `event_datetime` for Event, `name` (or default creation order) for
  Plotline/CodexEntry.
* Search runs against **raw stored values** — `Scene.contents` (Markdown source) and
  `Scene.notes` (sanitized rich-HTML source) directly, never `Scene::renderedContents`.

## Depends on

* Task 01 (`SearchMode` enum for the mode parameter type; `SearchSnippet` for building each
  row's snippet).

## Key decisions already made (binding, see `00-overview.md`)

* Exactly six entities/fields — no aliases, no attribute values.
* AND mode is cross-field per entity, not per-field.
* One row per matching field.
* LIKE escaping is required.
* Natural ordering, no relevance scoring.
* Cross-project isolation via the project_id / parent-chain scoping above — this is the
  authorization-adjacent invariant from `00-overview.md` that must hold even though this
  service itself doesn't do the `authorize()` call (that's task 03) — it must never be
  possible to pass a project and get another project's rows back.

## Docs to consult

* `expanded/architecture.md` → *Query shape*, *Where the logic lives* — this is exactly the
  class and query shape architecture.md describes.
* `expanded/data-model.md` → confirms no new indexes/migrations are needed; this task is
  pure query code.

## Tests

`tests/Unit/ProjectSearchTest.php` or `tests/Feature/ProjectSearchTest.php` (feature-style
since it needs `RefreshDatabase` + factories/DB — follow whichever existing service test in
this codebase is closest, e.g. check `AttributeTimelineTest.php` for a precedent of testing a
`Services/` class directly against the DB rather than through HTTP):

* One fixture per entity type with a distinctive term in each searchable field
  (`name`/`description`/`contents`/`notes`/`title` as applicable) — search for that term,
  assert every entity type is returned in the right group/column with the right field label.
* **AND mode, cross-field**: an entity with term A in one field and term B in a different
  field matches a two-term AND search for "A B".
* **AND mode, negative**: an entity with only term A (not B) does not match "A B".
* **OR mode**: entities containing *either* term match (superset of the AND-mode result on
  the same fixture).
* **Exact-phrase mode**: a multi-word phrase matches only rows containing it verbatim,
  word-order-sensitive (an entity with the words present but in a different order, or split
  across fields, does not match).
* **LIKE escaping**: a field containing a literal `%` or `_` is only found when searching for
  that literal character, and a search containing `%`/`_` does not accidentally match
  unrelated rows via wildcard behavior.
* **Multi-field match → two rows**: a Scene matching in both `contents` and `notes` produces
  two distinct result rows, each with its own field label and snippet.
* **Cross-project isolation**: a second project's matching entity never appears in the first
  project's results.
* **Ordering**: results within a column follow the documented natural order (e.g. seed Scenes
  out of position order, assert the returned order is by `position`).
* **No N+1**: assert a fixed, small query count regardless of how many rows match (e.g.
  `DB::enableQueryLog()` / an existing query-count assertion helper if the suite has one —
  check `StoryTest.php`/`AttributeTimelineTest.php` first).
* Search matches `Scene.contents` raw Markdown (e.g. a term inside `**bold**` asterisks is
  findable) and `Scene.notes` raw HTML, not any rendered form.
