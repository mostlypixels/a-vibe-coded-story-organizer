# 05 — Selection UI

## Scope

- One Alpine component holds the state `{sceneId: Set(indexes)}`, filled from the data in the page.
- The sticky bar has the counter ("N of M matches selected in K scenes"), "Select all", "Select none", "Unfold all", "Fold all" and "Replace N". "Replace N" is disabled at 0.
- Each scene header has a tri-state checkbox and "Replace in this scene".
- On submit, write the selection and the hashes into the hidden `selection` input. This replaces the "select everything" submit from 04.
- Above 50 selected matches, show an inline summary panel ("Replace 214 matches in 61 scenes?") before the post. Do not use `confirm()`.
- Vitest tests next to the JS source, for the state logic.
- Update `documentation/features/find-and-replace.md`.

## Depends on

- 04.

## Key decisions already made

- The state drives the checkboxes. Select all and none change the state, so folded scenes are included.
- "Replace in this scene" posts the same form, with only that scene in `selection`.
- Fold and unfold all set `open` on every `<details>`. They do not change the selection.
- Without JS, the 04 behaviour stays: everything is selected.

## Docs

- `expanded/ui.md` → Preview list, Alpine.

## Tests

- Vitest: toggling one match, a scene's tri-state, select all and none, the counter, the JSON shape.
- Feature: the page renders the bar and the data for Alpine. The submitted JSON from one scene changes only that scene.
