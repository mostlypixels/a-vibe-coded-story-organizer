# UI

## Panel order

1. **New scene** button (as now).
2. Search field (as now). Results replace items 3 and 4.
3. **Recent scenes**: a `<details>` styled like `card-section.blade.php` (small uppercase heading, rotating chevron).
4. **Outline**: `<nav aria-label="Outline">`.

`x-collapsible-card` is rejected: its `px-6` padding and shadow are for full pages, not a 200 px panel. The right-panel section style keeps both panels the same.

## Outline markup

- Act: a small heading (`text-xs uppercase`, like the section headings). Not foldable.
- Chapter: `<details>`, `open` only for the current chapter. `<summary>` shows the chapter label, truncated, with the full label in `title`.
- Scenes: `<ul>` of `<a>` rows inside the chapter. The scene name only, truncated. The current row: `aria-current="page"` and `bg-accent-surface text-accent-content`.
- Empty chapter: one muted line, "No scenes yet."
- Native `<details>` and links give Tab, Enter and Space for free. A `role="tree"` widget would need full arrow-key support to be correct, so it is not used.

## Scroll into view

- On init, if the panel is open, call `scrollIntoView({ block: 'nearest' })` on the current row.
- It must scroll the panel only, not the prose column or the page.

## Narrow screens

- Below `lg` the panel is a drawer (as now). The outline works the same. Section toggles there are not saved (as now).
