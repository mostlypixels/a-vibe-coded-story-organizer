# Task 12 — `<x-diff>`: the one place diff output is styled

## Scope

`resources/views/components/diff.blade.php`, props:

* `:html` — the renderer's output (or a stored `summary_html`);
* `:inline` — summary mode (one line, no block chrome), default false;
* `:kind` — `FieldKind`, so the source-diff (`jfcherng` side-by-side table) and the visual
  diff can share one component while keeping their own layout.

It owns **all** diff styling, so no view re-declares it and none of it can leak into
`x-rich-text` (author content):

* changed passages: background tint **plus** a `+` / `−` gutter mark **plus** a
  visually-hidden "inserted" / "removed" label — three redundant channels, because colour
  alone is not information and `<ins>`/`<del>` announcement is inconsistent across screen
  readers;
* **never** strikethrough or underline as a marker — the author can write both (`<s>`,
  `<u>` are in `RichTextFields::ALLOWED_TAGS`);
* a formatting-changed block renders an `x-badge` naming what changed
  ("formatting changed: bold added");
* rendered content keeps its real formatting (a heading looks like a heading), scoped so
  nothing inherits the editor's prose styles;
* the Markdown/Plain side-by-side table keeps today's two-column look, restyled through
  this component so both diff kinds read as one feature (move the utility classes
  currently inline in `revisions/compare.blade.php` here).

## Depends on

Task 6.

## Key decisions already made

* One component, both diff kinds — otherwise the two drift apart visually.
* The component **never** sanitises: its input is renderer output (task 6) or a stored
  `summary_html` produced by the same renderer. Binding decision 7.
* Colour pairs stay the existing `red-100/red-700`, `green-100/green-700` already used in
  compare, so contrast is a known quantity.

## Consult

* `expanded/ui.md` — *The diff rendering*, and the accessibility checklist.
* `resources/views/revisions/compare.blade.php` — the inline utility classes to move.
* `resources/views/components/badge.blade.php`, `rich-text.blade.php` — reuse and
  separation.

## Tests

`tests/Feature/DiffComponentTest.php` (Blade-render test, like
`tests/Feature/AutosaveFieldComponentTest.php`):

* rendering a diff containing `<ins>`/`<del>` emits the gutter mark and the `sr-only`
  "inserted"/"removed" text;
* no `text-decoration: line-through` / `underline` utility appears on a diff marker;
* `inline` mode renders one line with no block chrome;
* a formatting-changed block renders the badge with the mark named;
* both `kind` variants render without error.
