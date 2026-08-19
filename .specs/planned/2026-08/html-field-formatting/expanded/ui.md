# Interface

## Toolbar placement

Two more dropdowns in `resources/views/components/wysiwyg.blade.php`, after the Lists /
Callout / Code group and before the separator that precedes Link:

```
… Lists  Callout  Code │ Align  Colour │ Link  HR  Image │ Table
```

Both use the existing `x-wysiwyg.toolbar-dropdown`.

| Dropdown | Trigger icon | Active expression |
| --- | --- | --- |
| Align | `align-left` | `isOn('textAlign', {align: 'center'}) \|\| … ` — build it in `WysiwygToolbar` rather than hand-writing the chain in Blade |
| Colour | `palette` | `isOn('textColor')` |

Both icons exist in the `tabler-` family already used throughout the toolbar.

For a Markdown field the item arrays are empty and neither dropdown renders. That is the
only Blade-level difference, and it comes from data.

## Class contract

- `rt-align-center`, `rt-align-right`, `rt-align-justify` on `p`/`h1`–`h4`. Left is the
  absence of a class, so existing content needs no migration.
- `rt-color-<name>` on `span`.
- The `rt-` prefix keeps these out of Tailwind's namespace and makes them greppable in the
  sanitizer, the editor, `app.css`, and the EPUB stylesheet at once.

## Styling, three surfaces

The same class list must be styled in three places. State this in
`documentation/features/rich-text.md` as a keep-in-step list.

1. `resources/css/app.css` — plain CSS rules beside the `blockquote[data-callout-type]`
   block, not Tailwind utilities. They must apply inside `.prose` (editor and
   `x-rich-text`) and inside `.revision-diff--visual`, which deliberately opts out of
   `prose`.
2. `resources/views/exports/epub/styles.css` — the reader-overridable copy.
3. Nothing else. The static site exporter uses plain text.

## Palette definition

Each colour is one custom property with a light and a dark value; the class reads the
property. Alignment needs no property.

```css
:root {
    --rt-color-red: oklch(52% 0.19 27);
    /* … one per RichTextFields::TEXT_COLORS entry … */
}

@media (prefers-color-scheme: dark) {
    :root { --rt-color-red: oklch(75% 0.14 27); }
}

.rt-color-red { color: var(--rt-color-red); }
```

- The app's own dark themes are token-driven, not `prefers-color-scheme` — mirror whatever
  `ThemeTokens` does for its dark presets rather than inventing a second mechanism. If the
  palette must vary per theme preset, the colours become theme tokens, which is a larger
  change; see `open-questions.md`.
- The EPUB copy uses `prefers-color-scheme` because a reader engine has no theme tokens.
  An engine that ignores it falls back to the light value on white paper, which is the
  correct default.
- Six entries, one per hue family, taken from `PlotlineColors` darker shades so the app
  keeps one colour vocabulary. Do not offer both shades — the author is choosing a
  meaning, not a tint.

## Accessibility

- Colour and alignment never carry meaning alone. Nothing in the app reads them back; they
  are presentation only. No behaviour keys off them.
- A reader stylesheet overriding `.rt-color-*` wins because the EPUB rules are single-class
  selectors with no `!important`.
- The colour dropdown items show the swatch *and* the colour name, like the callout
  dropdown shows an icon and a label.
- `title`/`aria-label` come from the `title` key each item already carries.
