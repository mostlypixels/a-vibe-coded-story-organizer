---
title: "Task 09 — Panel resize and collapse"
---

# Task 09 — Panel resize and collapse

## Scope

- `resources/js/editor/layout.js` (Alpine `editorLayout`): side panel widths, side panel collapse, Codex and Notes section collapse, debounced PATCH to `preferences.editor-layout`.
- Resize handle in `x-editor-panel`.
- Small screens: drawers.

## Depends on

05, 08.

## Key decisions already made

- Handle: `role="separator"`, `aria-orientation="vertical"`, `tabindex="0"`, `aria-valuenow/min/max` in px, label "Resize left panel" / "Resize right panel".
- Pointer drag with pointer capture. Arrows: 16 px. Home/End: min/max. Enter: toggle collapse. Double-click: default width.
- Collapsed panel: a 40 px rail with an expand button (`aria-expanded`, `aria-controls`).
- Sections use `<details>`; listen to `toggle` to update the layout.
- One PATCH 500 ms after the last change, full shape. A failure keeps the UI state and does not retry.
- Below `lg`: both panels start collapsed regardless of stored state; an open panel overlays the prose with a scrim; Escape closes it; widths are not applied and changes there are not saved.
- CSS caps each panel at `40vw`.

Detail: `expanded/ui.md` → *Layout*, *Resize handle*; `expanded/data-model.md`.

## Tests

- `resources/js/editor/layout.test.js`: clamp to min/max; key steps; collapse toggle; section toggle; many changes send one PATCH; small-screen mode sends none.
- `EditorTest.php`: handles have the separator attributes and the stored values.
