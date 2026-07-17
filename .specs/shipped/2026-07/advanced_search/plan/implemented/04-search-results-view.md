# Task 04 — Search results view

## Scope

Build the actual page: `resources/views/search/index.blade.php` plus a new reusable
`x-search-result-table` component, matching the app's existing Blade/Tailwind conventions.

* Standard authenticated layout (`x-app-layout`), matching `story/index.blade.php`.
* **Form**: plain `GET` form to `route('projects.search.index', $project)`, using existing
  `x-input`/`x-input-label`/`x-primary-button` components (no new form components). The mode
  control is a `<fieldset>`/`<legend>` radio group (or labeled `<select>`) over
  `SearchMode::cases()` — not unlabeled buttons (keyboard accessibility, `CLAUDE.md` §
  Frontend). `q` and `mode` reflect back from the current request (`old('q', ...)`-style or
  the controller-passed `$query`/`$mode`) so the form shows what was searched.
* **Results grouping**: three `<section>`s with real `<h2>` headings — "Timeline", "Story",
  "Codex" — each rendered only when `$results` is not null.
* **Extract a namespaced component set for the repeating structure** (binding — user directive,
  see `resolution-log.md`). The page repeats a *section → 3-column grid → result table → result
  row* structure; do **not** inline/duplicate that markup. Create a `search/` component subfolder
  (`resources/views/components/search/`) with dot-namespaced components:
  * **`x-search.section`** — one `<section>` with its `<h2>` heading (a `title` prop) wrapping
    the `grid grid-cols-1 md:grid-cols-3 gap-4` column grid; columns go in the default slot.
    Rendered once per section (Timeline / Story / Codex), so the grid classes live in exactly
    one place.
  * **`x-search.result-table`** — one column: a column label (e.g. "Scenes", "Characters") and
    its rows. Used for all 8 columns (2 + 3 + 3). Internally reuses
    `x-table`/`x-table-row`/`x-table-heading`/`x-table-empty`
    (`resources/views/components/table*.blade.php`) — do not re-implement table chrome.
  * **`x-search.result-row`** — one result row (see *Result row* below), so the single
    deliberate `{!! !!}` lives in exactly one component rather than being repeated per column.
* **Grid layout**: `grid grid-cols-1 md:grid-cols-3 gap-4` per section (inside `x-search.section`).
  Story and Codex fill all 3 columns; Timeline fills 2 (Plotlines, Events) and leaves the 3rd
  empty — do not special-case Timeline into a 2-column grid.
* **Result row** (`x-search.result-row`): entity name linking to its existing edit page (e.g.
  `route('scenes.edit', $scene)`), a muted small field-name label below it (e.g. "Notes"), and
  the pre-built snippet HTML rendered with `{!! !!}` — this is the **one** deliberate `{!! !!}`
  on this page, and it lives only in this component; `entityName` and `fieldLabel` stay `{{ }}`.
* **Empty states**: no query yet → just the form. Query submitted, zero matches anywhere → one
  page-level friendly message (mirror the `empty_states` feature's existing copy style —
  check `.specs/shipped/empty_states/` and the Codex index's empty-state text before writing
  new copy), not three separate per-section "no matches" blocks.

## Depends on

* Task 03 (controller must pass `project`, `query`, `mode`, `results` in the shape this view
  expects — coordinate the exact `results` structure with what task 02/03 actually produced
  rather than assuming; read the finished `SearchController`/`ProjectSearch` code first).

## Key decisions already made (binding, see `00-overview.md`)

* 3-column grid per section; Timeline's 3rd column stays empty.
* Codex is 3 columns (one per `CodexEntryType`), not 1 combined column.
* One row per matching field (already reflected in the data by task 02 — this task just
  renders it).
* `<mark class="bg-sun-200">` highlighting comes pre-built from `SearchSnippet` — this task
  does not re-implement highlighting.
* No AJAX.

## Docs to consult

* `expanded/ui.md` — this task implements essentially all of it (form, grouping, columns,
  result row, highlighting style, accessibility, empty states).
* `expanded/architecture.md` → the `{!! !!}` / escaping note under *Query shape*.

## Tests

Extend `tests/Feature/SearchTest.php` (from task 03) with view-content assertions:

* A submitted query with known fixtures renders the entity names, muted field labels, and
  `<mark>`-wrapped highlighted terms in the response HTML (`assertSee`, `assertSeeHtml` as
  appropriate for the raw `<mark>` tag).
* A scene whose `contents`/`notes` contains something like `<script>alert(1)</script>` around
  a matched term does **not** render as executable/unescaped markup in the response — assert
  the raw `<script>` tag is absent/escaped in the output (`assertDontSee` on the raw tag, or
  assert the escaped entity form is present instead).
* Entity name and field label render escaped even when the underlying entity name contains
  HTML-special characters (e.g. a scene named `<b>Test</b>` shows literally, not bolded).
* Empty query renders the form with no results section/empty-state message.
* Zero-match query renders the single page-level empty-state message, not three per-section
  ones.
* Timeline section renders with its 3rd column empty/absent while Story/Codex render all 3.
* Edit links point at the correct existing routes for each entity type (e.g. a matched Scene's
  row links to `route('scenes.edit', $scene)`).
* Keyboard/semantic checks that are practical via HTTP assertions: mode control is inside a
  `<fieldset>` (or uses a `<select>`), section headings are `<h2>`.
