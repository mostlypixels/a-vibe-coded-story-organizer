# Overview

## User stories

- I ask Claude to review a scene in the terminal. I go back to the browser, and the review note is in Notes without a page reload.
- Claude sets a codex attribute. The codex card shows the new value when I come back.
- I am not sure the panel is current. I click the refresh icon of the section.

## Acceptance criteria

- Window `focus`, or `visibilitychange` to `visible`, refreshes each open section of the right panel.
- A section whose `<details>` is closed does not refresh. Opening it refreshes it.
- Each section header has a refresh icon. It refreshes that section only.
- A refresh shows the same cards a page reload would: order, states, newly linked notes, gone notes and entries, current codex values.
- No visible change when nothing changed: no flicker, the focus and the scroll position stay.
- A card toggle, close or pick made before or during a refresh is not undone by the refresh.
- One focus return makes at most one request per section, also when `focus` and `visibilitychange` both fire.
- A failed refresh keeps the old cards and shows the section's error line. The next trigger tries again.

## Invariants touched

- Card states stay in `editor_cards`, written only by `EditorCard::saveState()`. A refresh only reads.
- The `EditorCards` query count must not grow with the card count (pinned in `tests/Feature/EditorTest.php`). This also applies to the refresh endpoint.
- The scene text is out of scope. Autosave owns it.
