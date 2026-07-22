# Task 01 — Backend allow-list widening

## Scope

Widen `App\Support\RichTextFields` so its allow-list can express the new constructs:

- Add `table`, `thead`, `tbody`, `tr`, `th`, `td` to `ALLOWED_TAGS`.
- Add `img` to `ALLOWED_TAGS`.
- Add whatever tag(s) `TaskItem`/`TaskList` render (confirm the actual `renderHTML`
  output from the installed `@tiptap/extension-list` package — likely `ul`/`li` are
  already allowed, plus a checkbox-state attribute) to `ALLOWED_TAGS`/`ALLOWED_ATTRIBUTES`.
- Add a new `ALLOWED_ATTRIBUTES` constant: `array<string, list<string>>` mapping tag →
  allowed attribute names (e.g. `'img' => ['src', 'alt', 'title', 'width', 'height']`,
  `'a' => ['href']` — moving the existing `a[href]` special case into this same
  mechanism instead of leaving it as a one-off ternary).
- Update `purifierAllowedHtml()` to build its `HTML.Allowed` directive from
  `ALLOWED_ATTRIBUTES` for every tag that has entries, falling back to a bare tag name
  for anything not in the map — replacing the current single `$tag === 'a' ? 'a[href]' : $tag`
  ternary with a general lookup.
- Add a `data-callout-type` (or equivalent) attribute to `ALLOWED_ATTRIBUTES['blockquote']`
  for the callout node (task 06 implements the node itself; this task only needs to
  make the sanitizer accept the attribute it will emit).

This task does **not** touch the editor, any Blade view, or any converter
(`ValidMarkdown`, `EpubExporter`) — those are tasks 02–06.

## Depends on

None. Pure PHP, no dependency on the JS-side work.

## Key decisions already made

- **Applies to both formats.** `RichTextFields::ALLOWED_TAGS`/`ALLOWED_ATTRIBUTES` is
  the single shared allow-list for the 8 HTML `RichTextFields` fields — `Scene.contents`
  never touches this (it stays Markdown-only, gated by `ValidMarkdown`), but every
  construct decided in this feature (tables, images, task lists, callouts) must be
  representable here since they're editor output for the HTML-mode fields.
- **`ALLOWED_ATTRIBUTES` is a new, separate constant** — do not merge it into
  `ALLOWED_TAGS`'s flat list structure.
- Do not add `width`/`height` restrictions or validation beyond what HTMLPurifier's
  directive syntax already provides — this task is allow-list widening, not a new
  validation layer.

## Docs to consult

- `../expanded/architecture.md` (`RichTextFields` section) for the proposed tag list and
  the `purifierAllowedHtml()` mechanism gap.
- `documentation/rich-text.md` for the current allow-list table and the "MUST stay a
  superset of what the editor's slash menu can produce" invariant (`RichTextFields`'s
  own docblock states this too — do not weaken it).

## Tests

- Extend `tests/Unit/RichTextTest.php` (or add a new `RichTextFieldsTest.php` if none
  exists covering `RichTextFields` directly — check first) asserting:
  - `purifierAllowedHtml()`'s output includes `table`, `img[src|alt|title|width|height]`
    (exact attribute set per the constant above), and the task-list tag/attribute.
  - A fragment using each new tag survives `HtmlSanitizer::clean()` unchanged.
  - A negative case (`<iframe>`, `<script>`) is still stripped — guards against an
    over-broad edit.
- Extend `tests/Feature/RichTextRenderingTest.php` with an HTTP-level save of a rich
  field (e.g. `Project.description`) containing a `<table>`/`<img>` fragment, asserting
  it persists unchanged.
