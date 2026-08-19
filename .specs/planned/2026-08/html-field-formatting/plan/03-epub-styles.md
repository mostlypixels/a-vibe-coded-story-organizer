# 03 — EPUB styles

The same class list, in a book. A reading system has no theme tokens, so the values are
fixed here — the one place in this feature literals are correct.

**Depends on:** 01.

## Scope

- Rules in `resources/views/exports/epub/styles.css` for every entry of
  `decorativeClasses()`.
- Light values: take the **Daylight** preset's values for the five tokens named in
  `00-overview.md`, copied as literals with a comment saying where they came from.
- A `@media (prefers-color-scheme: dark)` block with the Dusk preset's values. A reading
  system that ignores the query falls back to the light values on white paper, which is the
  right default.
- Single-class selectors, no `!important`, so a reader's own stylesheet wins.

**Not in scope:** the static site export — it renders project descriptions through
`RichText::toPlainText()`, so classes never reach it. Confirm that before assuming; if a
later view changes it, that is a new task.

## Key decisions

- Literals here, tokens in the app (task 02). The duplication is real and deliberate: an
  EPUB is a static file with no theme system. Comment the source preset so a future palette
  change knows to update both.
- Codex entry descriptions reach `exports/epub/appendix-entry.blade.php` through
  `RichText::toXhtmlFragment()`, which does not strip classes. Nothing in the exporter needs
  changing — only this stylesheet.

## Consult

`expanded/ui.md` → *Styling, three surfaces*. `config/themes.php` for the two presets'
values. `documentation/export-import/epub.md`.

## Tests

- `EpubExporterTest`: the packaged `styles.css` defines a rule for every entry of
  `decorativeClasses()`. Loop the registry.
- End-to-end: a codex entry whose description carries `rt-align-center` and `rt-color-red`
  reaches `appendix-entry-*.xhtml` with both classes intact.
- The counterpart, and the acceptance criterion that matters most: a **scene body**
  carrying the same classes reaches the EPUB with them stripped. That single pair of tests
  is what "decoration in appendices, never in the narrative" means in practice.
