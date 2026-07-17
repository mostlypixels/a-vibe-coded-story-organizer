# UI

## Page: `resources/views/search/index.blade.php`

Standard authenticated layout (`x-app-layout`, matching `story/index.blade.php` and friends).

### Form

A plain `GET` form (no `x-data`/fetch — the spec is explicit this isn't AJAX):

```blade
<form method="GET" action="{{ route('projects.search.index', $project) }}">
    <x-input type="text" name="q" value="{{ $query }}" placeholder="Search..." />
    <!-- mode: radio group or select, one <x-input-label> per SearchMode::cases() -->
    <x-primary-button type="submit">Search</x-primary-button>
</form>
```

Reuse the existing `x-input`, `x-input-label`, `x-primary-button` components (Breeze
defaults already used across `events/`, `plotlines/`, etc. — don't invent new form
components, per `CLAUDE.md` § Tailwind). `q` and `mode` round-trip via the query string on
submit (`GET`), so results are shareable/bookmarkable and survive a page refresh.

### Results — grouping

Three `<section>`s, one per nav grouping, only rendered when `$results` is not null and that
section has at least one match:

* **Timeline** — Plotlines, Events
* **Story** — Acts, Chapters, Scenes
* **Codex** — Codex entries (all `CodexEntryType` cases together, `type` shown as part of the
  muted field-name-style label, e.g. "Character" / "Location" / "Organization" prefix — codex
  entries aren't literally a "field" but the same muted-label slot communicates it)

Each section heading matches the nav label exactly ("Timeline" / "Story" / "Codex") for
consistency with `CLAUDE.md` § *Reuse existing project conventions*.

### Results — "3 columns"

The spec says each section is "divided in 3 columns each (for now)". Read literally against
the entity list above:

* Timeline has exactly 2 entity types (Plotlines, Events).
* Story has exactly 3 (Acts, Chapters, Scenes).
* Codex has 1 model but 3 `CodexEntryType` cases.

**Recommendation**: one column per entity *kind* — Story's 3 columns are Acts / Chapters /
Scenes; Codex's 3 columns are Characters / Locations / Organizations (reusing
`CodexEntryType::cases()`, so a 4th type added later grows the grid rather than requiring a
redesign); Timeline only fills 2 of the notional 3 (leave the 3rd empty, or let it span 2 —
see `open-questions.md`, since the spec doesn't fully resolve this and it's worth confirming
before building the grid). Each column is its own `x-table` instance, laid out with a
responsive grid (`grid grid-cols-1 md:grid-cols-3 gap-4`), matching the "Favor reusable
components" convention — build one `<x-search-result-table>` component parameterized by
entity label + rows, used for all 6 columns rather than duplicating markup six times.

### Result row

Reuse `x-table` / `x-table-row` / `x-table-heading` / `x-table-empty` (`resources/views/components/table*.blade.php`)
— the existing card-wrapped, striped table family used everywhere else in the app:

```blade
<x-table>
    <x-slot:head>
        <x-table-heading>Match</x-table-heading>
    </x-slot:head>
    @forelse ($column->rows as $row)
        <x-table-row :striped="$loop->even">
            <td>
                <a href="{{ $row->editUrl }}" class="font-medium">{{ $row->entityName }}</a>
                <div class="text-xs text-gray-400">{{ $row->fieldLabel }}</div>
                <p class="text-sm">{!! $row->highlightedSnippet !!}</p>
            </td>
        </x-table-row>
    @empty
        <x-table-empty :colspan="1">No matches</x-table-empty>
    @endforelse
</x-table>
```

`$row->highlightedSnippet` is pre-escaped HTML with `<mark>` around matched terms (built by
`SearchSnippet`, see `architecture.md`) — this is the one deliberate `{!! !!}` in the page,
same pattern as `Scene::renderedContents` (`documentation/architecture.md`): the escaping
happens once, centrally, before the view ever sees it, not ad hoc in Blade. **Never**
interpolate `$row->fieldLabel` or `$row->entityName` with `{!! !!}` — those stay auto-escaped
`{{ }}`.

`$row->editUrl` links to the entity's existing edit page (e.g. `route('scenes.edit', $scene)`,
`route('projects.codex-attributes.edit', ...)` as applicable) — clicking a result should take
the writer straight to where they'd fix it, matching the "As a writer... so I know where to
go fix something" story in `overview.md`.

### Highlighting style

`<mark>` is the semantically-correct element for "highlighted for reference" (MDN); style it
with a small Tailwind utility class matching the app's `sun`/`flame` accent palette already
used elsewhere (e.g. `bg-sun-200`, `#ffe494` — the `sun` palette's `200` shade was filled in
during the pre-ship review; `bg-sun-400` is the table-header color) rather than the browser
default yellow, for visual consistency with the rest of the UI.

### Accessibility

* The mode control (AND/OR/exact) needs a `<fieldset>`/`<legend>` (radio group) or a labeled
  `<select>` — not a div of unlabeled buttons — per `CLAUDE.md` § Frontend (keyboard
  accessibility, semantic HTML).
* Section headings are real `<h2>`s so screen-reader users can jump between Timeline/Story/Codex.
* `<mark>` is already accessible by default (announced by most screen readers as
  "highlighted"); no extra ARIA needed.

### Empty states

Two distinct empty states, matching the `empty_states` feature's existing conventions
(`.specs/shipped/empty_states/`):

* No query yet (`$results === null`): just the form, no "no results" messaging.
* Query submitted, zero matches anywhere: one page-level friendly message (mirroring the
  Codex/index "no entries yet" copy from `empty_states`), not three separate empty
  `x-table-empty` sections repeating the same "no matches" text.
