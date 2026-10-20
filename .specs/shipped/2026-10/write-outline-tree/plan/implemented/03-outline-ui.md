# 03 — Outline UI

## Scope

- New `resources/views/editor/partials/outline.blade.php`, included below the recent section.
- `search.js`: the browse view (recent + outline) hides while a query is typed. Rename `showingRecent()` to `showingBrowse()` if it reads better; update callers and tests.
- On init, when the left panel is open, scroll the current row into view inside the panel only (`block: 'nearest'`).
- Update `documentation/features/editor.md`: left panel contents, `sections.recent`, outline query cost.

## Depends on

- 01, 02.

## Key decisions already made

- See `00-overview.md` → Binding decisions.
- `<nav aria-label="Outline">`. Act: small uppercase heading, only when `! hasSingleAct()`. Chapter: `<details>`, `open` only for the current chapter. `<summary>`: truncated label, full label in `title`, word total on the right.
- Scene row: `<a>` to `scenes.write`, truncated name, current row `aria-current="page"` with `bg-accent-surface text-accent-content`.
- Empty chapter: muted "No scenes yet."

## Docs

- `expanded/ui.md`; `expanded/overview.md` → Acceptance criteria.

## Tests

- `EditorTest`: every scene of the book in story order across acts; act headings hidden for one act and shown for two; only the current chapter open; one `aria-current` row in the outline; other books absent; empty chapter line; chapter word total.
- `search.test.js`: query hides browse view, clearing shows it.
