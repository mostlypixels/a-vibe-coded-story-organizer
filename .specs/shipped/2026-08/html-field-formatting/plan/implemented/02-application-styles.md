# 02 — Application styles

What the classes look like on screen. Still nothing produces them; this task is verified by
source and by rendering stored HTML.

**Depends on:** 01.

## Scope

- Rules in `resources/css/app.css`, beside the existing `blockquote[data-callout-type]`
  block. Plain CSS, not Tailwind utilities.
- `rt-align-center|right|justify` → `text-align`. No rule for left.
- `rt-color-<name>` → `color: var(--color-<token>)`, using the token table in
  `00-overview.md`. **No literal colour values in this task.**
- The rules must apply in three places: inside `.prose` (the editor surface and
  `x-rich-text`), and inside `.revision-diff--visual`, which deliberately opts out of
  `prose` and so inherits nothing from it.

**Not in scope:** the EPUB stylesheet (task 03). The diff *tokenizer* (task 06) — this task
only makes sure a coloured span already stored in a field renders correctly wherever the
existing views put it.

## Key decisions

- Tokens, not literals. This is the whole reason the palette costs nothing in dark mode and
  in all four presets; a hard-coded hex here silently discards it.
- Selector specificity has to beat `.prose`'s own colour rules for `color`. Check the built
  output rather than assuming — `@plugin '@tailwindcss/typography'` is loaded before these
  rules and `.prose` sets `--tw-prose-*` on descendants.

## Consult

`expanded/ui.md` → *Class contract*, *Styling, three surfaces*, *Palette definition*.
`documentation/interface/themes.md` for how tokens reach the page.

## Tests

- A CSS source test in the manner of `resources/js/css-source-smoke.test.js`: every entry of
  `RichTextFields::decorativeClasses()` has a rule, and every `rt-color-*` rule references a
  `var(--color-*)` rather than a literal.
- `RichTextRenderingTest`: a stored description carrying both classes renders them through
  `x-rich-text` unescaped.
