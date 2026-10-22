# Write Panel Refresh — plan

## Order

| Task | Purpose |
|---|---|
| `01-section-endpoint.md` | Server: one section's cards as HTML, from the same partial as the page load |
| `02-client-refresh.md` | Client: refresh on focus, on section open and on the icon, with the guards |

## Binding decisions

- One refresh icon per section, beside the `<summary>`, not inside it.
- The sections refresh also while the right panel is collapsed. A section whose `<details>` is closed does not.
- A change during the request drops the response. No retry: the next trigger refreshes.
- No minimum interval. One running refresh per section coalesces `focus` and `visibilitychange`.
- Deleted notes and entries vanish from the cards with no extra code: `editor_cards` cascades on delete, and there is no soft delete.
- No websockets, SSE or timed polling.

## Invariants

- A refresh only reads. `EditorCard::saveState()` stays the only writer of `editor_cards`.
- The `EditorCards` query count does not grow with the card count: page load and each section.
- The page load and the endpoint render the same cards partial.
- The endpoint authorizes `update` on the scene's project.
