# 03 — Quick switcher and docs

## Scope

- `AppearanceSwitcherController`: `page_width` in `steps`, `labels` (`ScaleLabel` `width` format) and `active` (integer).
- `appearance/switcher.blade.php`: own "Width" row with the existing − / + markup; labels "Narrower page" / "Wider page".
- Docs: "Page width" in `documentation/interface/` (section in `fonts.md` or new `page-width.md` linked from the interface README). Say why: percent of window, independent of text size.

## Depends on

- 02.

## Key decisions already made

- Own section, not under a font section.
- The control stays visible on phones; no hint.

## Docs

- `expanded/ui.md` → Quick switcher; `expanded/architecture.md` → Pitfall, Docs.

## Tests

- `AppearanceSwitcherTest`: fragment has the width row; `active.page_width` is an integer.
- `appearance-switcher.test.js`: `neighbourSlug` clamps at 50 and 100; 70 + 1 → 80.
