# 02 — Client refresh

## Scope

- `refresh()` in `resources/js/editor/cards.js`, with the guards from `expanded/architecture.md` → Client.
- Listeners: window `focus`, document `visibilitychange` (to `visible` only), the section `<details>` `toggle` (open only). Remove them in `destroy()`.
- The refresh icon and the busy state, `refreshUrl` and the failure string in `editor/partials/card-section.blade.php`.

## Depends on

- 01

## Key decisions already made

- Skip when the `<details>` is closed or a refresh is running. Do not check the panel state.
- Wait for the running saves (a `Set` of promises in `save()`).
- `changeSeq` bumps in `setState()`, `close()` and `pick()`. A moved counter drops the response. No retry.
- Compare element children with `isEqualNode()`, not HTML strings, and ignore whitespace text nodes. The cards hold no Alpine.
- Replace only `$refs.cards`. Put focus back on the same `data-card-id` + `data-card-action` if it was inside.
- Failure: keep the cards, set `loadError` to `__('The cards could not refresh. Check your connection and try again.')`.
- Icon: `x-icon-button`, `icon="refresh"`, ghost, label `Refresh :section`, beside the `<summary>` (`summary` gets `flex-1`), hidden while closed, `motion-safe:animate-spin` while busy.

## Docs

- `expanded/architecture.md` → Client, Pitfall.
- `expanded/ui.md`.

## Tests

- `expanded/testing.md` → JS.
