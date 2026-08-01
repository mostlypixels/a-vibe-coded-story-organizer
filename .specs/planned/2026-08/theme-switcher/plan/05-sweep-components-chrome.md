# 05 — Sweep: chrome components

First sweep slice, and the one that sets the pattern. ~140 usages.

## Scope

`resources/views/components/` — the structural family: `button`, `badge`, `card`, `table`,
`sortable-header`, `alert`, `heading`, `breadcrumbs`, `dropdown`, `dropdown-link`, `modal`,
`popover`, `dialog`, `diff`, `search/result-row`.

Also introduces `tests/Feature/NoHueNamedColorsTest` with an allow-list covering every path not
yet swept.

Does **not** include: form controls, pickers, `autosave-*`, `revision-*`, `icon-*`, nav/sidebar
links — task 06.

## Depends on

02, 03, 04.

## Key decisions already made

- **`x-button` has seven variants** (`primary`, `secondary`, `danger`, `success`, `warning`,
  `ghost`, `link`), and four `focus:ring-ocean-500` occurrences. Every one lands on `focus`,
  never `primary`.
- **`x-badge`**: `indigo` → `accent-surface` / `accent-content`; `gray` → `neutral` (also the
  `@props` default, so every callsite omitting `variant` moves with it). `indigo`'s only caller
  is `revision-origin-badge` for `RevisionOrigin::Import` — it must stay **visually distinct
  from `info`**, which that same component uses for `Manual`.
- **`x-table`'s `<thead class="bg-sun-400">` is `table-header`**, not `highlight`. It is the
  header band on every table in the app; `SearchSnippet`'s own comment says so.
- `search/result-row`'s `<mark class="bg-sun-200">` → `highlight`. The class is *also* written
  from PHP (`SearchSnippet::HIGHLIGHT_CLASS`) — change both, or the mark silently stops
  matching when the ramp goes.
- `bg-white` panels → `surface-raised`; `modal`/`dropdown` panels → `surface-overlay` (the same
  value in Daylight, deliberately).

## Consult

`expanded/architecture.md` → *Migration map* (the full table), `expanded/ui.md` → the sweep
table.

## Tests

- `NoHueNamedColorsTest` — scans `resources/views`, `resources/js`, `resources/css`, `app/` for
  `ocean|navy|aqua|sun|flame|gray|slate` + shade number, plus `text-white`/`bg-white`, minus an
  explicit allow-list of unswept paths. **Must be green** — `master` is protected and every PR
  needs a green `tests` check, so it ships with the allow-list, not red. This slice's paths come
  off the list.
- Existing suites stay green, especially `BladeComponentCompilationTest`,
  `IconButtonComponentTest`, `FormControlComponentTest`, `DiffComponentTest`.
- Eyeball the swept components under the rough dark preset — anything still light was missed.
