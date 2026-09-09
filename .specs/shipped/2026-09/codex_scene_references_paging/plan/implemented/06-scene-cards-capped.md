# 06 — Cap the scene-side lists

## Scope

- `codex/partials/referenced-entries.blade.php`: render `entry-row`, cap at
  `config('search.cap')`, add the footer link. The partial now takes `$scene` as well as
  `$referencedEntries` — the link needs a route parameter.
- `SceneCodexEntryController::store()`: pass `$scene` when rendering the partial to JSON,
  so the fragment swapped into the editor keeps the cap and an accurate count.
- `scenes/show.blade.php`: drop its inline `<ul>` — a duplicate of the partial — and
  `@include` the partial instead.
- `scenes/edit.blade.php`: no markup change; the `x-collapsible-card` stays and the cap
  arrives through the partial.

## Depends on

05.

## Key decisions

- The cap lives inside the partial, not in `scenes/edit.blade.php`. The partial has three
  callers, one of which is an AJAX refresh; capping outside it means adding an entry from
  the editor swaps in an uncapped fragment and the list grows past the cap until reload.
- Entry rows show name + type. No cover thumbnail.
- Footer link text and the at-cap rule are the same as task 04.

## Consult

`expanded/ui.md` → *Cards*, *Two row components*.
`SceneCodexEntryController::store()` for the JSON path;
`resources/js/quick-codex-entry.test.js` for what the JS expects of the fragment.

## Tests

- A scene referencing 6 entries with `search.cap` at 5: card renders 5 rows plus the
  footer link, 6th name absent. Both `scenes/edit` and `scenes/show`.
- Exactly `cap` rows: no footer link.
- Quick-codex-entry `store()` response HTML is capped and carries the footer link when the
  new entry pushes the count past the cap.
- Existing `quick-codex-entry` JS test still passes.
