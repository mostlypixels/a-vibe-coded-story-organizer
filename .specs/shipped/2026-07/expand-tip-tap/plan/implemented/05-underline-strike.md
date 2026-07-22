# Task 05 — Underline & Strikethrough in Markdown mode

## Scope

Stop suppressing `Underline`/`Strike` in Markdown-mode fields, and give `Underline` the
custom Markdown handler it needs (unlike Strike, which already round-trips as plain
GFM once task 02 fixes the PHP-side converters):

- Remove `strike: false, underline: false` from the
  `...(isMarkdown ? { strike: false, underline: false } : {}) ` StarterKit override in
  `resources/js/wysiwyg.js`'s `init()` — this conditional becomes empty and should be
  deleted entirely, not left as `isMarkdown ? {} : {}`.
- Give `Underline` a custom `parseMarkdown`/`renderMarkdown` pair emitting/reading
  literal `<u>text</u>` (CommonMark's raw-inline-HTML passthrough already parses this —
  no `ValidMarkdown` change needed for this specific case). Confirm the exact config
  shape (`{tag: 'u'}` object vs. an explicit render function) against
  `@tiptap/markdown`'s actual mark-config API at implementation time.
- Add a one-line comment at this extension's config explaining *why* `<u>` specifically
  is the one sanctioned HTML-passthrough exception in an otherwise fully-tokenized
  Markdown field.
- Strike needs **no** custom handler — `~~text~~` is standard GFM and `marked`
  (`@tiptap/markdown`'s parser) already handles it. This task's Strike work is purely
  "stop suppressing an already-working mark."
- Slash menu (`buildSlashItems()`): drop `mdHide: true` from both the `'Underline'` and
  `'Strikethrough'` entries.
- Toolbar (`wysiwyg.blade.php`): move the `if (! $markdown) { ...push Underline/Strike... }`
  block's two `$toggles[]` pushes into the unconditional base array (alongside
  Bold/Italic), removing the `$markdown`-conditional branch entirely — it becomes the
  only `$toggles`-affecting conditional removed by this feature.

## Depends on

Task 03 (vitest tier must exist to add this task's round-trip tests) and task 02
(EpubExporter/ValidMarkdown must already accept GFM strikethrough for an end-to-end save
of struck-through Markdown content to mean the same thing everywhere).

## Key decisions already made

- Underline gets a **custom** handler; Strike does **not** — don't add one
  unnecessarily to Strike out of a false symmetry with Underline. They're solved
  differently because they started from different gaps (`spec.md`'s "Underline: decided
  — keep it, via `<u>` HTML passthrough" vs. "Strikethrough: decided, not blocked on the
  pivotal unknown").
- The `<u>` passthrough is explicitly **the one sanctioned exception** in an otherwise-
  tokenized Markdown field — don't generalize this pattern to any other mark without a
  fresh decision.

## Docs to consult

- `../expanded/architecture.md` §4–5 (Underline round-trip, Strikethrough) for the exact
  StarterKit-override diff and the rationale for why each needs (or doesn't need) a
  custom handler.
- `../expanded/spec.md`'s "Underline" and "Strikethrough" bullets under "Rough approach"
  for the full decision history.

## Tests

- Extend `resources/js/wysiwyg.test.js`: a Markdown-mode document containing
  `<u>text</u>` round-trips through hydrate → `getMarkdown()` → re-hydrate unchanged; a
  document containing `~~text~~` does the same.
- Confirm the slash menu's item list for `markdown` format now includes both
  `'Underline'` and `'Strikethrough'` (regression-guard against the `mdHide` filter
  reappearing).
- `tests/Unit/Rules/ValidMarkdownTest.php` (from task 02) already covers the
  server-side acceptance half; no new PHP test needed here unless a gap is found.
