# Resolution log — Empty states

## Design: one shared component, two states

The spec asked for friendly empty-state copy on the Codex index and "the same treatment"
on events/acts/chapters/scenes via a shared `x-table-empty`. The existing component only
emitted a bare "No X match." row — which reads wrong for a *genuinely empty* collection
(there is nothing to "match" yet).

**Resolution.** `x-table-empty` now branches on a `filtered` prop the view computes from
`request()->hasAny([...])`:

- **not filtered** → friendly "No :items yet." headline + a primary CTA button pointing at
  the resource's create action (`createUrl` + `createLabel`);
- **filtered** → "No :items match your search or filters." with no CTA (the toolbar's own
  *Clear* link is the way back).

All five indexes pass `items` (already-translated plural noun) and, where a genuinely-empty
collection is reachable, the create URL/label. The per-view `@empty` slots were dropped once
the component's default copy covered them, so each call site is just props — no duplicated
message strings.

## Notes / edge cases

- **Events is never genuinely empty.** A fresh project always carries the Start/End bookend
  events, so the events index only ever reaches the *filtered* branch. The CTA config is still
  passed for consistency but is effectively unreachable there; the feature test for events
  therefore asserts the filtered message, not the empty CTA.
- **Codex noun casing.** `CodexEntryType::pluralLabel()` returns a capitalised label
  ("Characters"); the mid-sentence copy needs lower case, so the view passes
  `\Illuminate\Support\Str::lower($type->pluralLabel())` as `items`.

## Verification

`composer test` → 239 passed (796 assertions), including the new `EmptyStateTest` (7 tests)
which drives the real index views over HTTP and asserts the empty-vs-filtered copy and that
the Codex empty state links to the type-specific create route. Component and CHANGELOG docs
updated (`documentation/ui-components.md`).
