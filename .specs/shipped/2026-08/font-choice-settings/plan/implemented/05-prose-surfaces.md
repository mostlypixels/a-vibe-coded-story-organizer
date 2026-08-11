---
title: "Task 05 — Prose surfaces"
---

# Task 05 — Prose surfaces

## Scope

Make the manuscript variables reach the page: `rich-text`, the `wysiwyg` editable area,
and the `.prose` rule.

No new wrapper element, no change to any layout, no picker (task 07).

## Depends on

04 (the variables must exist).

## Key decisions already made

* `resources/views/components/rich-text.blade.php` — add `font-manuscript` to the merged
  `prose` class list.
* `resources/views/components/wysiwyg.blade.php` — same on the **editable area**, so what
  is typed matches what is read. The **toolbar keeps `font-sans` and the UI scale**: it
  is chrome inside a manuscript surface (overview decision 7).
* `.prose` in `resources/css/app.css` gains `line-height: var(--manuscript-leading)` and
  `font-size: var(--manuscript-scale)`, beside the `--tw-prose-*` re-pointing already
  there. Same unlayered-after-the-plugin trick; the existing comment block explains why
  that wins — don't restate it, extend it.
* The leading applies to the whole block, so headings inside prose inherit it. Intended:
  a manuscript with a loose body and tight headings reads wrong.
* Leave `max-w-none` alone — measure is out of scope, a future width container owns it.
* Exports (`exports/epub/styles.css`, `exports/book/layout.blade.php`) stay untouched.

## Consult

* `expanded/ui.md` → *Prose surfaces*, including its Tailwind Typography warning
  (the override must come after `@plugin '@tailwindcss/typography'` — it already does)

## Tests to add

Extend `tests/Feature/ThemeRenderingTest.php` or the relevant component test:

* A page rendering `x-rich-text` carries the `font-manuscript` class.
* The wysiwyg editable area carries it; the toolbar does not.

CSS-only assertions belong in `FontConfigTest`-style file greps, not in a browser test —
but do run `npm run build` and check one editor page in the app: Typography's per-element
`line-height` is exactly the kind of thing a green suite misses.
