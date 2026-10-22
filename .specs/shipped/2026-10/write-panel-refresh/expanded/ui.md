# UI

## Refresh icon

- `x-icon-button` with `icon="refresh"`, `variant="ghost"`, label `__('Refresh :section', ['section' => $heading])`.
- Put it in a flex row with the `<summary>` (`summary` takes `flex-1`), at the end of the section header line. Not inside the `<summary>`.
- Hidden while the `<details>` is closed (`group-open/section:` classes). A refresh of a closed section does nothing.
- While a refresh runs: `aria-busy="true"` on the cards container, and the icon spins (`motion-safe:animate-spin`). No text, no "Loading…" line: the old cards stay usable.

## Feedback

- Failure: the existing `loadError` line with a new string, `__('The cards could not refresh. Check your connection and try again.')`.
- Success: no message. A screen reader gets no announcement. The change is in the cards themselves.

## Unchanged

- Card markup (`x-editor-card`) and its `data-card-*` hooks.
- The search field and its results. A refresh replaces only `$refs.cards`.
