# 05 — Toolbar and slash menu

The two dropdowns. First task where a writer can see the feature.

**Depends on:** 01, 04. Do 02 first if you want to see it working rather than only passing.

## Scope

- `WysiwygToolbar::alignment()` and `textColor()`, both returning `[]` when
  `$this->markdown`. Item shapes follow `callouts()` — the `action` / `active` / `icon` /
  `label` / `title` keys the dropdown already consumes.
- `alignment()` includes a `left` reset item; `textColor()` includes a *Remove colour* item.
- Build the dropdowns' `active-expression` in `WysiwygToolbar`, not by hand in Blade — the
  existing `isOn('a') || isOn('b')` chains in `wysiwyg.blade.php` are already at their limit.
- `x-wysiwyg.toolbar-dropdown` returns nothing for an empty item list. It currently assumes
  a non-empty array.
- Two dropdowns in `resources/views/components/wysiwyg.blade.php`, placed per
  `expanded/ui.md` → *Toolbar placement*. Trigger icons `align-left` and `palette`, both
  already in the `tabler-` set.

**Not in scope:** any Blade conditional on format. The empty arrays are the gate; if you
find yourself writing `@unless ($markdown)`, the data is wrong.

## Key decisions

- The colour items show a swatch **and** the colour name, as the callout items show an icon
  and a label. Colour alone is never the only signal, including in our own chrome.
- `title` supplies both `title` and `aria-label`, as `x-wysiwyg.toolbar-button` already does.

## Consult

`expanded/ui.md` → *Toolbar placement*, *Accessibility*.
`expanded/architecture.md` → *Toolbar data*.

## Tests

- `WysiwygToolbarTest`: `alignment()` and `textColor()` are non-empty for HTML and empty for
  Markdown; their values come from the `RichTextFields` constants, not literals.
- `WysiwygFormTest`: a Markdown-format field's rendered toolbar contains no `setTextAlign`
  and no `setTextColor`. The scene contents field is the case that matters.
- The parity assertion named in `00-overview.md`: toolbar and slash menu are gated by the
  same boolean. Assert the two lists agree rather than testing each separately.
- `x-wysiwyg.toolbar-dropdown` with `:items="[]"` renders nothing and does not error.
