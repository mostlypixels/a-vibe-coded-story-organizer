# UI — Revision History Rework

Everything renders inside the existing `<x-revisions-layout>` shell (sidebar + content
pane). Reuse first: `x-table`, `x-badge`, `x-button`, `x-card`, `x-heading`,
`x-revision-origin-badge`, `x-revert-revision-button`, `x-table-empty`. Tailwind palette
as used elsewhere (`ocean` for links/actions, `flame`/`aqua`/`navy` for accents).

## 1. History page — `resources/views/revisions/index.blade.php` (rewritten)

Header: `History — Scene "The Ferry"`, with *Back to editing* on the right (unchanged).

Controls row:

* **Field filter** — a small `<select>` (native is right here: no filtering panel, five
  options at most) listing *All fields* plus the fields that actually have revisions,
  submitting via GET so the state stays in the URL (`?field=`). This replaces the removed
  per-field pages, and mirrors how the label search already submits.
* **Label search** — the existing `?label=` search input, kept.
* **Compare latest two** — kept, now linking to the entity-level compare with the two
  most recent save points.

The list is **not** an `x-table`: a save point is a two-level thing (the save, then the
fields it touched) and a table row cannot hold that without nested tables. It is a stack
of `x-card`-like rows in a new partial, `revisions/partials/save-point.blade.php`:

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ 24 July 2026, 10:43   Cindy Durand   [Saved]  "Saved 24 July 10:43"          │
│                                                          [Current]  [Undo…]  │
│ ── Description ──────────────────────────────────────────────────────────────│
│   …the ferry <del>left</del><ins>slipped away</ins> at dawn…                 │
│   and 3 more changes ›                                                       │
│ ── Contents ─────────────────────────────────────────────────────────────────│
│   …<ins>She counted the gulls.</ins>…                                        │
└──────────────────────────────────────────────────────────────────────────────┘
```

Per row:

* **Header line**: date (`d F Y H:i`), author, `x-revision-origin-badge` for the save
  point's dominant origin, its label if any. `x-badge variant="success"` **Current** when
  the save point is the entity's live state.
* **Entries**: one line per field the save touched — field name (`Str::headline`), the
  stored `summary_html` rendered through `<x-diff inline>`, and when
  `change_count > 1` a link "and N more changes" to `revisions.compare` with
  `from`/`to`/`field` prefilled.
* **Actions**: *Compare with previous* (→ `revisions.compare?from=<previous>&to=<this>`),
  and **Undo this save** unless the point is current — a POST form to
  `revisions.saves.revert` carrying one `base_hashes[<field>]` hidden input per field in
  the group, plus a `confirm` (the existing `x-delete-button` confirm pattern, reworded:
  *"Undo this save? Every field it changed goes back to its previous value. Nothing is
  deleted — the undo is recorded as a new save."*).
* **Baseline** save points keep their dedicated treatment: an italic "Initial value —
  before revision history" line instead of summaries.

Pagination: the standard Laravel paginator links below the stack.
Empty state: `x-table-empty`'s wording, adapted to a non-table container.

## 2. Compare page — `resources/views/revisions/compare.blade.php` (rewritten)

```
┌────────────────────────────────┬─────────────────────────────────┐
│ Older                          │ Newer                           │
│ [ ▼ #12 · 23 Jul 21:04 · auto ]│ [ ▼ #18 · 24 Jul 10:43 · Saved ]│  ← comboboxes
├────────────────────────────────┴─────────────────────────────────┤
│ Description                                       [Restore ←][→] │
│   <diff>                                                          │
├───────────────────────────────────────────────────────────────────┤
│ Contents                                          [Restore ←][→] │
│   <diff>                                                          │
├───────────────────────────────────────────────────────────────────┤
│ 2 other fields unchanged (Notes, Rights)                          │
└───────────────────────────────────────────────────────────────────┘
```

* **One section per changed field**, in `AutosavableFields::REGISTRY` order, each a
  `x-card` with the field's headline as title. Unchanged fields collapse into a single
  muted line at the bottom, so the page answers "what differs" without noise.
* When `?field=` is set, only that section renders and a *Show all fields* link clears it.
* Each section carries the per-field restore buttons (`x-revert-revision-button`, existing
  component, existing POST route) for the older side — hidden when that side is current.
* Below the pickers, a one-line summary of the pair: *"12 saves apart · 23 July 21:04 →
  24 July 10:43"*.
* Empty states: fewer than two save points → the existing "Nothing to compare yet" panel;
  two identical snapshots → "These two saves left every field identical."

### The diff rendering — `<x-diff>` (new component)

`resources/views/components/diff.blade.php`, props `:html`, `:inline` (summary mode),
`:kind`. It owns **all** diff styling so no other view re-declares it, and so the diff CSS
can never bleed into `x-rich-text` (author content):

* changed passages: background tint **plus** a `+` / `−` gutter mark **plus**
  `<span class="sr-only">inserted</span>` / `removed` — three redundant channels, because
  colour alone is not information and `<ins>`/`<del>` announcement is inconsistent across
  screen readers (`notes/revision-ui-lexicon.md` §4).
* **never** strikethrough or underline as markers — the author can write both (`<s>`,
  `<u>` are in `RichTextFields::ALLOWED_TAGS`).
* a formatting-only block gets an `x-badge` — *"formatting changed: bold added"* — beside
  the rendered block, since the visible difference may be subtle.
* rendered content keeps its real formatting (headings look like headings) but is scoped
  so nothing inherits from the editor's own prose styles.

The Markdown/Plain source diff keeps today's side-by-side table, restyled through the same
component so both diff kinds look like one feature.

## 3. The save-point combobox — `x-revision-picker`

`resources/views/components/revision-picker.blade.php` + `resources/js/revision-picker.js`
(Alpine), built to the **W3C APG select-only combobox pattern** — a native `<select>`
cannot hold the filter panel, and a bare `<div>` dropdown tells a screen reader nothing.

Contract:

| Concern | Behaviour |
|---|---|
| Roles | `role="combobox"` on the trigger, `role="listbox"` on the panel, `role="option"` per row |
| State | `aria-expanded`, `aria-selected`, `aria-controls`, `aria-activedescendant` maintained on every interaction |
| Keyboard | ↓/↑ move the active option, Enter selects, Escape closes and returns focus to the trigger, Home/End jump, typing filters |
| Option label | `#<n> · <date> · <label> · <origin hint>` + a **Current** marker on the live state |
| Filters (in the panel) | *Manual saves only* toggle, and a from/to date range — **independent per side, deliberately not synced**, so a bad save can be found by comparing a manual save against the autosaves around it |
| Constraint | The right picker disables every option not strictly newer than the left selection — the invalid state is unreachable rather than validated afterwards |
| Navigation | Selecting an option sets `from`/`to` in the URL and navigates (the page is a pure GET; no client-side diffing) |
| No-JS | The component degrades to a plain `<select>` inside a `<form method="GET">` with a *Compare* submit button |

The option list is server-rendered from `RevisionHistory::savePoints()` — payload size is
not the constraint here, human scanability is; a project with thousands of save points is
handled by the in-panel filters, not by paging the dropdown.

## 4. Entry points elsewhere

* **Edit pages**: the existing per-field History icon now links to
  `revisions.index?field=<field>` (same target, new URL). Add one entity-level *History*
  link in `x-edit-actions`' Actions card, pointing at `revisions.index` with no filter —
  this is the new primary entry point.
* **Revisions browser sidebar** (`revisions/partials/sidebar.blade.php`): leaves keep
  their per-field counts but link to `revisions.index?field=`; the **entity name itself
  becomes a link** to the unfiltered entity history. Collapse/filter behaviour unchanged.
* **Flash messages**: `status = 'reverted'` (per field, existing) and
  `status = 'reverted-save'` (new) — the latter names the restored fields, e.g.
  *"Restored Description and Contents from the save of 24 July 10:43."*

## 5. Accessibility checklist (EAA, and the project's own rules)

* Semantic markup: the save-point list is a `<ul>`/`<li>`, each diff section an
  `<article>` with a heading; the "unchanged fields" line is plain text, not a disabled
  control.
* Keyboard: every action reachable and operable; focus visible; Escape closes the picker.
* Screen readers: `sr-only` "inserted"/"removed" on every marked passage; the picker's
  ARIA state contract above; `aria-current="page"` kept on the active sidebar leaf.
* Colour: tint + glyph + text on every change; contrast checked against the existing
  `red-100/red-700`, `green-100/green-700` pairs already used in compare.
* Motion: none introduced.
