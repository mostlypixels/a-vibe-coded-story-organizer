# Task 10 — `RevisionHistory`: save points, paginated and filtered

## Scope

Value objects in `app/Support` (readonly, following `RevisionDiffResult`):
`SavePoint`, `SaveEntry` — fields as sketched in `expanded/architecture.md`.

`App\Services\RevisionHistory` (following the `ProjectSearch` service pattern):

* `forEntity(Model $entity, array $filters, int $page): LengthAwarePaginator` of
  `SavePoint`, where `$filters` accepts `field`, `label`, `manualOnly`;
* `savePoints(Model $entity, array $filters = []): Collection<SavePoint>` — the option
  list the compare pickers render (task 14/15).

Query shape (portable, two steps, **`value` never selected**):

1. `SELECT save_id, MAX(created_at) AS saved_at, MAX(id) AS last_id … GROUP BY save_id
   ORDER BY saved_at DESC, last_id DESC LIMIT per_page+1 OFFSET …` — the extra group is
   the boundary row used only to build the last rendered row's "compare with previous"
   link, then dropped;
2. `SELECT id, save_id, field, created_at, user_id, label, origin, size_bytes,
   summary_html, change_count … WHERE save_id IN (…)`, `with('user:id,name')`.

Fold rows into `SavePoint`s in PHP: entries in `AutosavableFields::REGISTRY` field order,
group label = first non-null label, group origin = the most deliberate one by the fixed
precedence `manual > revert > import > automatic > baseline`, `isCurrent` = the newest
save point of the entity (with no field filter applied, so filtering never mislabels it).

Pagination is a hand-built `LengthAwarePaginator` over a `COUNT(DISTINCT save_id)`, per
page from `config('revisions.history.per_page')` (new key, default 20).

No routes, no views — task 13 renders this.

## Depends on

Tasks 2, 9.

## Key decisions already made

* **No window functions, no `COALESCE`+concat, no `GROUP_CONCAT`** — grouping happens in
  PHP so all five engines run the same plan.
* **No null `save_id` branch** — task 1 deleted the legacy rows, so every row in the table
  carries one (binding decision 4).
* **Filtering stays in the service, not in Eloquent scopes** (CLAUDE.md's index-page rule).
* `manualOnly` filters on `origin = manual`; it is shared with the pickers, so it lives
  here rather than in the controller.
* The list never selects `value`.

## Consult

* `expanded/architecture.md` — *`App\Services\RevisionHistory`*, and the value-object
  sketches.
* `app/Services/ProjectSearch.php` — the service pattern to mirror.
* `app/Services/ProjectRevisionsBrowser.php` — the existing "never select `value`"
  grouped-query precedent.

## Tests

`tests/Unit/Services/RevisionHistoryTest.php` (unit-level, hitting the DB via
`RefreshDatabase`):

* rows written by one save fold into one `SavePoint` listing both fields;
* ordering is newest-first, with ties inside one second broken by id;
* `field` / `label` / `manualOnly` filters each narrow correctly, and combine;
* `isCurrent` marks the newest save point, and still does so when a field filter is
  applied;
* group origin precedence: a group holding a `manual` and an `automatic` row reads as
  `manual`;
* pagination respects `config('revisions.history.per_page')` (set it small in the test);
  the boundary group is used for the link and not rendered as a 21st row;
* a query listener asserts no executed select against `revisions` includes `value`.
