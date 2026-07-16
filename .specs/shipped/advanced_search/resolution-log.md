# Advanced search — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- Codex results render as 3 columns, one per `CodexEntryType` (not one combined column).
- Timeline keeps the 3-column grid for visual consistency with Story/Codex; its 3rd column is
  left empty rather than collapsing to a 2-column layout.
- `CodexAlias.alias` and `CodexAttributeValue.value` are explicitly out of scope for v1.
- Result ordering within a column uses each entity's existing natural order (position /
  event_datetime / name) — no relevance scoring.
- A matching entity produces one result row per matching field, not one row per entity.
- AND mode is satisfied per-entity across fields (a term can match in any field), not
  required within a single field.
- User-supplied `%`/`_` in search terms must be escaped before building `LIKE` patterns, so
  literal percent/underscore characters in a query don't act as SQL wildcards.
- Quoted sub-phrases inside AND/OR mode are out of scope for this feature; a follow-up draft
  spec (`search_pagination`... actually a separate concern — see note below) was not created
  for this specifically, but is worth a future draft spec if requested.
- Default search mode on first page load is AND (`SearchMode::AllTerms`).
- A companion draft spec, `.specs/draft/search_pagination/spec.md`, was written for a future
  feature (capping result counts per column + a paginated per-domain "see all results" page).
  It explicitly depends on this feature shipping first and is out of scope here.
- **Complete the custom Tailwind palettes rather than route around gaps** (user directive during
  the pre-ship review). When a needed shade is missing, add it to `tailwind.config.js` instead of
  substituting a different shade. All five custom palettes (`ocean`/`aqua`/`navy`/`sun`/`flame`)
  are now filled to the full `50–950` range; interpolated/extrapolated values are noted below.
- **Extract reusable Blade/Tailwind components for the search page's repeating structure** (user
  directive). The page repeats a section → 3-column grid → result-table → result-row structure;
  build components for it (see task 04) instead of duplicating markup, reusing the existing
  `x-table*` primitives underneath.

## Deviations from the spec/plan

- Highlight color: the plan originally specified `bg-sun-200`, which did not exist in the
  palette. Rather than change the design, the `sun-200` shade was **added** to
  `tailwind.config.js` (see below), so the highlight stays `bg-sun-200` as originally intended.

## Issues → resolutions

- **`bg-sun-200` was a non-existent Tailwind class** (found during the pre-ship double-check of
  the planning work). The `sun` palette in `tailwind.config.js` is a fully custom color under
  `theme.extend.colors` whose scale skipped `200` (it defined 50/100/300/400/500/600/700). The
  plan and `expanded/ui.md`/`open-questions.md` specified `<mark class="bg-sun-200">`, which
  Tailwind would not generate — the highlight would have rendered with **no background**, a
  silent UI failure that still passes every PHPUnit `assertSeeHtml('bg-sun-200')`-style test.
  **Resolution (per user directive):** the missing shade was **added** to the palette rather than
  routing around it. `sun-200 = #ffe494`. While there, all five custom palettes were completed to
  the full `50–950` range, filling every gap (values interpolated/extrapolated from existing
  neighbors): `sun` +200/800/900/950, `flame` +200/800/900/950, `navy` +200/300/400, `aqua` +950.
  Tailwind's JIT only emits CSS for classes actually used, so the added tokens are purely
  additive (no CSS-bundle growth) and prevent this class of "class silently does nothing" bug.
  The highlight stays `bg-sun-200` throughout the plan/docs/code.
- **`PlotlineFactory` produced flaky tests via random color collisions** (surfaced by task 02's
  `ProjectSearchTest::test_search_runs_a_fixed_number_of_queries…`, which uses
  `Plotline::factory()->count(3)->for($project)`, and flagged during task 04's run). The
  `unique(project_id, color)` constraint forbids two plotlines in one project sharing a color,
  but the factory drew a *random* preset color and its DB-based de-dup was ineffective: with
  `->for()` the `project_id` is absent from the closure's `$attributes` (so de-dup was skipped),
  and a `->count()` batch is not persisted incrementally (so siblings can't be seen). Result:
  ~10% collision for `count(3)`, and a latent ~3% for any same-project pair (e.g. EventTest,
  ExportTest). **Resolution:** replaced random selection with a process-wide **round-robin** over
  the palette (`PRESETS[1..]`, excluding the main plotline's reserved `PRESETS[0]`), collision-free
  for any realistic per-project count. Critical detail: the counter lives in the `definition()`
  **method body**, not in a `color` closure — `->count(N)` calls `definition()` once per instance
  and a method `static` is shared across those calls, whereas each per-instance closure gets its
  own `static` (all N would then pick index 0 and collide 100% of the time — verified this failure
  mode before moving the counter). Confirmed with 25× repeat runs of the previously-flaky test
  (25/25 pass) plus the full `ProjectSearchTest`/`EventTest`/`PlotlineTest` suites.

## Task 01 — SearchMode enum + SearchSnippet helper

### Feedback & decisions
- `SearchMode` labels: `'Match all words'` / `'Match any word'` / `'Exact phrase'` (AllTerms /
  AnyTerm / ExactPhrase, backed values `all` / `any` / `exact`).
- `SearchSnippet::highlight($text, string|array $terms, int $length = 120)` is the public entry
  point; window length lives in the `SearchSnippet::CONTEXT_LENGTH = 120` constant and the
  highlight class in a private `HIGHLIGHT_CLASS = 'bg-sun-200'` constant (single source).

### Deviations
- None. Highlight color is `bg-sun-200` (`#ffe494`); the shade was added to `tailwind.config.js`
  during the pre-ship review (see the palette note above) rather than substituting another shade.

### Issues → resolutions
- **Escape-then-highlight without offset drift.** Escaping the whole string first would shift
  byte offsets and force escaping the search terms too. Resolution: slice the raw window, then
  `preg_split` on the term alternation with `PREG_SPLIT_DELIM_CAPTURE` and `e()`-escape each
  segment individually, wrapping only the captured (odd-index) match segments in `<mark>`. Raw
  HTML (`<script>`) in the source can never become live markup — the only tags in the output
  are our `<mark>` wrappers. Covered by tests (script-tag and `&`/`<` escaping cases).
- Multibyte-safe throughout (`mb_stripos`/`mb_substr`, `/u` regex flag) so the ~120-char window
  and offset math work on non-ASCII scene text.

## Task 02 — ProjectSearch service

### Feedback & decisions
- **Result shape:** `App\Services\ProjectSearch::search(Project, string $query, SearchMode): SearchResults`.
  `App\Support\SearchResults` is a value object with one named `Collection<SearchResultRow>` per
  column (`plotlines`, `events`, `acts`, `chapters`, `scenes`, `characters`, `locations`,
  `organizations`) plus `all()` / `count()` / `isEmpty()`. Codex is pre-split into the three
  per-type columns here (one `CodexEntry` query, partitioned in PHP by `->type`), so task 04's view
  stays a dumb renderer. `App\Support\SearchResultRow` carries `{Model $entity, string $fieldLabel,
  string $snippet}` (snippet = pre-escaped highlighted HTML from `SearchSnippet`, the sole `{!! !!}`
  value). Task 04 should consume this shape directly.
- **Field-match check is mode-agnostic:** an entity returned by the SQL is expanded into one row per
  field whose raw value contains any term (case-insensitive `mb_stripos`). The same check serves all
  three modes — the mode only changes *which entities the SQL returns*, not how a returned entity is
  split into field rows. This is what makes the "Scene matching in both contents and notes → two
  rows" and the AND-cross-field cases fall out naturally.
- **Scoping:** `Act`/`Event`/`Plotline`/`CodexEntry` filter on `project_id` directly; `Chapter` via
  `whereHas('act', …project_id)`; `Scene` via `whereHas('chapter.act', …project_id)`. `whereHas`
  compiles to a correlated `EXISTS` subquery, so the whole search is exactly 6 SELECTs regardless of
  match count (asserted by `test_search_runs_a_fixed_number_of_queries…`).
- **Ordering:** `position` (+`id` tiebreak) for Act/Chapter/Scene; `event_datetime`+`id` for Event
  (matches `EventController@index`'s default); `name` for Plotline/CodexEntry.

### Deviations
- None. Implemented exactly as scoped in `02-project-search-service.md` / `architecture.md`.

### Issues → resolutions
- **`Illuminate\Database\Query\Builder::whereLike` (Laravel 12/13) does NOT escape wildcards.**
  Checked per the task note: `whereLike($col, $value)` binds `$value` as the raw LIKE pattern and
  attaches no `ESCAPE` clause, so a literal `%`/`_` in the value would still act as a wildcard. It is
  therefore unusable for the escaping requirement. Resolution: hand-rolled `orWhereRaw("$col like ?
  escape '\\'", ['%'.addcslashes($term,'%_\\').'%'])` — the only builder path that emits an `ESCAPE`
  clause. Column names come from private constants (never user input) so embedding them in the raw
  fragment is safe; the pattern is always a bound parameter. Covered by
  `test_like_wildcards_in_a_term_are_escaped_and_match_literally` (a row containing `1950` must not
  match the term `50%`, and `axb` must not match `a_b`).
- **Cross-driver `ESCAPE` caveat (documented, not blocking):** the generated SQL literal is
  `escape '\'` (a lone backslash), which is correct on the test driver (SQLite) and on pgsql/sqlsrv.
  MySQL treats backslash as special *inside string literals*, so on MySQL the clause would need
  `escape '\\'`. The plan's binding decision explicitly chose the backslash escape char and the
  suite runs on SQLite, so this is followed as specified; flag for future hardening if the app is
  ever pointed at MySQL (the escape char is centralized in `ProjectSearch::ESCAPE_CHARACTER`).
- **Field-match check must be case-insensitive to mirror LIKE.** SQLite's `LIKE` is
  case-insensitive for ASCII, so a case-sensitive PHP `str_contains` would have dropped rows the SQL
  legitimately matched (returning zero field rows for a real match). Used `mb_stripos` throughout so
  the PHP split agrees with the SQL match and is multibyte-safe.

## Task 03 — Route, SearchController, SearchRequest

### Feedback & decisions
- Route: `GET /projects/{project}/search` named `projects.search.index`, inside the `auth` group
  right after the story route.
- `SearchRequest::rules()`: `q => ['nullable','string','max:500']`, `mode => ['nullable',
  Rule::enum(SearchMode::class)]`. `mode` is nullable (not required) so the controller can apply the
  AND default; an invalid value still fails validation.
- Controller reads the mode with `$request->enum('mode', SearchMode::class) ?? SearchMode::AllTerms`
  and uses `blank($query)` (which trims) to detect the empty/whitespace landing state — so `q=''`,
  `q='   '`, and an absent `q` all pass `results = null` with no validation error.
- Landed a **minimal placeholder** `resources/views/search/index.blade.php` (a bare form + result
  count) so the controller's `view('search.index', ...)` resolves and the feature tests render. It is
  explicitly marked for replacement by task 04; it renders only the four passed variables
  (`project`, `query`, `mode`, `results`) and introduces no new Tailwind classes or JS.

### Deviations
- `expanded/architecture.md`'s controller snippet references `SearchMode::All` and
  `$request->validated('mode', ...)`; the real enum case is `SearchMode::AllTerms` (per
  `00-overview.md` and the enum) and the default is applied via `$request->enum(...) ?? AllTerms`.
  Used the correct case name — the architecture snippet's `::All` was a shorthand slip, not a
  binding decision.

### Issues → resolutions
- **`route($name, $project, ['q' => ...])` silently drops the query string.** The `route()` helper's
  third argument is `$absolute` (bool), not extra query params — passing the model as the 2nd arg and
  an array as the 3rd meant `q`/`mode` never reached the request, so `results` came back `null` and
  the happy-path/default-mode tests failed. Resolution: use the single-array form
  `route('projects.search.index', ['project' => $project, 'q' => 'zephyrqux'])` so extra keys become
  the query string. A green PHPUnit run would NOT have caught this if the test had only asserted
  `assertOk()` — it was caught because the test asserts on `viewData('results')`.
- The default-mode test asserts AND semantics through the controller (a plotline matching both terms
  is returned; one matching a single term is excluded) via `viewData('results')`, not the placeholder
  markup, so it stays valid when task 04 replaces the view.

## Task 04 — Search results view

### Feedback & decisions
- **Namespaced component set** built under `resources/views/components/search/` (user directive):
  `x-search.section` (the `<section>` + `<x-heading level="2">` + the single
  `grid grid-cols-1 md:grid-cols-3 gap-4`), `x-search.result-table` (one column, wrapping the
  existing `x-table`/`x-table-heading`/`x-table-row`/`x-table-empty` primitives), and
  `x-search.result-row` (the SOLE `{!! $row->snippet !!}` render on the page). No repeating markup
  is inlined in `search/index.blade.php` — it just lays out 8 `x-search.result-table` calls inside
  3 `x-search.section`s.
- **Component prop names vs. the spec's placeholders.** `ui.md`/task 04 referenced `x-input` /
  `x-primary-button` / `$row->entityName` / `$row->editUrl` / `$row->highlightedSnippet` as
  illustrative names. The real reusable components are `x-text-input`, `x-input-label`, and
  `x-button variant="primary"`, and the actual value object is `SearchResultRow{entity, fieldLabel,
  snippet}`. Used the real ones (spec was explicit its snippets were sketches to reconcile against
  the finished code).
- **Per-column edit route + display field passed as props**, not resolved by magic. Each
  `x-search.result-table` gets an `edit-route` (e.g. `scenes.edit`, `codex.edit`) and a `name-field`
  (default `name`; Events pass `title`) so `x-search.result-row` builds `route($editRoute, $entity)`
  and reads `$entity->{$nameField}` with no per-row query and no class-sniffing.
- **Mode control** is a `<fieldset>`/`<legend>` radio group over `SearchMode::cases()` (keyboard
  accessible), reflecting the current `$mode` via `@checked`.
- **Empty states**: landing (`$results === null`) shows only the form; a zero-match search shows ONE
  page-level friendly message (`No results match "…"`), gated on `$results->isEmpty()`, not three
  per-section blocks. A per-*column* empty within an otherwise-non-empty search still shows the
  standard `x-table-empty filtered` row (that is per-column, not the forbidden per-section block).

### Deviations
- None functionally. Only the illustrative component/prop names from the spec sketches were mapped to
  the codebase's real component names (see above).

### Issues → resolutions
- **`bg-sun-200` now genuinely resolves — verified in the *built* CSS and the live browser, not just
  PHPUnit.** This was the exact class the pre-ship review flagged as a silent-failure risk. Confirmed
  `public/build/assets/app-*.css` contains `.bg-sun-200{…background-color:rgb(255 228 148…)}` and, in
  a real Chromium session on the rendered page, `getComputedStyle(mark).backgroundColor` returned
  `rgb(255, 228, 148)` (= `#ffe494`). A green PHPUnit `assertSeeHtml('bg-sun-200')` alone would NOT
  have proven this — it only proves the class string is emitted, not that it maps to CSS.
- **`public/hot` must be absent for the built assets to load.** The working tree had a stale
  `public/hot` (leftover dev-server pointer) while no `npm run dev` was running, so `@vite` would
  point every asset at the dead dev server and the page would render unstyled (highlight invisible).
  Removed it before browser verification so the app served `public/build`. Symptom this catches: a
  perfectly correct view that looks broken only in the browser, with tests still green.
- **View-content tests must key off results-only markers, not section words.** The desktop nav
  already renders the words "Timeline"/"Story"/"Codex"/"Plotlines"/… (dropdown labels), so
  `assertSee('Timeline')` / `assertDontSee('Timeline')` are meaningless on this page. Resolution: the
  empty/landing tests assert on `md:grid-cols-3` (a class unique to the search sections) to detect
  whether result grids rendered, and the "Timeline leaves its 3rd column empty" test counts
  `scope="col"` occurrences (`substr_count == 8`: Timeline 2 + Story 3 + Codex 3) — proving Timeline
  contributes exactly two columns, not three.

### Observed (pre-existing, NOT task 04)
- **`ProjectSearchTest::test_search_runs_a_fixed_number_of_queries…` is intermittently flaky.** It
  failed once, then passed on re-run and in the full `composer test` (574 passed). Root cause is in
  `PlotlineFactory` + the test's `Plotline::factory()->count(3)->for($project)`: the factory's
  colour-dedup closure reads `$attributes['project_id']`, but `->for()` supplies the FK via the
  relationship (not in `$attributes`), so the closure falls back to `randomElement` over all presets
  and can draw a duplicate colour, tripping the `unique(project_id, color)` constraint (birthday
  collision, ~random). This predates and is independent of task 04 (view-only), so it was left for the
  task-02 owner rather than fixed here; flagged so it isn't lost. Suggested fix: resolve the project
  from the factory's `recycle`/relationship, or assign colours by preset index instead of random.
  *(Resolved before ship — see the round-robin `PlotlineFactory` fix in the top-level
  Issues → resolutions section.)*

## Task 05 — Nav integration

### Feedback & decisions
- Search is a plain `x-nav-link` / `x-responsive-nav-link` (not a dropdown), placed **last** in
  both menus, after the Story dropdown — consistent with "Home" being the only other
  non-dropdown top-level item.
- `$searchActive = request()->routeIs('projects.search.*')` was added to **both** duplicated
  `@php` blocks (desktop + responsive), following the existing `$storyActive`/`$codexActive`
  pattern documented in `documentation/architecture.md` → *Navigation active state* — no new
  inline `routeIs()` calls scattered in the template.
- The link carries `aria-current="page"` when active (via the `:aria-current` binding), matching
  what `NavigationTest`'s `assertLinkIsCurrent` helpers assert on — never colour-only.

### Deviations
- None. Implemented exactly as scoped in `05-nav-integration.md`.

### Issues → resolutions
- None. All three required tests (search page marks the link current and leaves the other
  sections inactive; a non-search page leaves it present but not current; the `href` resolves to
  `route('projects.search.index', $project)`) reuse the existing `assertLinkIsCurrent`/
  `assertLinkIsNotCurrent` helpers, per the task's convention note. Full suite green after the
  task: 577 passed (2137 assertions).

## Post-ship changes

### 2026-07-16 — Hide columns (and sections) with no matches

User directive, superseding two of the plan's binding decisions ("every section renders as a
3-column grid … Timeline fills 2 and leaves the 3rd empty" and the per-column
`x-table-empty filtered` row):

- A column with no matches now renders **nothing** — `x-search.result-table` returns early on
  an empty collection, and its `items` prop / `x-table-empty` branch were removed as dead.
- A section whose columns are all empty is skipped entirely (no orphaned `<h2>` over an empty
  grid). The per-section check lives on the value object — `SearchResults::hasTimelineMatches()
  / hasStoryMatches() / hasCodexMatches()` — so the Blade stays logic-free.
- The section grid **stays** `md:grid-cols-3` even when fewer columns render, so column widths
  keep lining up across sections.
- Tests: `test_all_three_sections_render_with_timeline_leaving_a_third_column_empty` was
  replaced by three tests (`only_columns_with_matches_render`,
  `sections_with_no_matches_are_hidden`, `a_single_matched_section_renders_alone`) that count
  `scope="col"` headings and `<section` elements — both markers are unique to the search
  results on this page (the nav renders the section *words*, so word-based assertions stay
  meaningless here, per the task-04 lesson).
