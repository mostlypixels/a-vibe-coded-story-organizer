# 05 — Scene reorder JS module

## Scope

Move `moveScene` and `updateSceneMoveButtons` out of `resources/js/app.js` into
`resources/js/scene-reorder.js`, and make a reorder keep the two scene numbers correct.

## Depends on

04 (needs the `data-scene-number` span in the story overview).

## Key decisions

- **Swap the two spans' text, not the nodes.** The `<section>` carries its own number span,
  so moving the sections already moves the numbers with them; swapping the nodes as well
  cancels out and nothing changes on screen.
- Only two numbers ever change. Two scenes adjacent inside one chapter are adjacent in the
  project-wide sequence too, so a move swaps exactly their two numbers and touches no other
  row. No reload, no recompute, no new response field — `SceneController::reorderResponse`
  keeps answering `{position: …}` and the client keeps ignoring it.
- The story overview calls it from an inline `onclick`, so the module must still assign
  `window.moveScene`. `app.js` imports it alongside the existing `register*` modules.
- Extraction is what makes it testable: `app.js` is the Vite entry and pulls in Alpine and
  axios, and every existing JS test is per-module.

## Tests

`resources/js/scene-reorder.test.js` (new, vitest):

- A successful move swaps the two `data-scene-number` values along with the two sections,
  and leaves every other scene's number alone.
- A failed `PATCH` moves nothing and changes no number.
- Move buttons at the ends of a chapter stay disabled after a reorder.
