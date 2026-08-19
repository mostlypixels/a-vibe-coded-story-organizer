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

**Superseded by the grill — see `plan/00-overview.md` → Binding decisions.** Five colours,
each taking its value from a theme token that `ThemeTokens::PAIRS` already contrast-checks
against every page surface in every preset:

| Class | Token |
| --- | --- |
| `rt-color-red` | `--color-danger-surface-content` |
| `rt-color-green` | `--color-success-surface-content` |
| `rt-color-amber` | `--color-warning-surface-content` |
| `rt-color-blue` | `--color-info-surface-content` |
| `rt-color-grey` | `--color-content-subtle` |

```css
.rt-color-red { color: var(--color-danger-surface-content); }
```

- **No `--rt-color-*` custom properties, and no `prefers-color-scheme` in `app.css`.** The
  theme system already swaps these tokens per preset, dark presets included. A second
  mechanism beside it is the thing to avoid.
- Every preset keeps the same hue and moves only lightness/chroma, so the colour *names*
  stay honest across all four.
- The EPUB stylesheet is the exception and does use literals plus `prefers-color-scheme`,
  because a reading system has no tokens. See task 03.
- The earlier draft of this section proposed six new hues copied from `PlotlineColors` with
  hand-written dark values. Rejected: it discards free contrast-checking and free dark mode.

## Accessibility

- Colour and alignment never carry meaning alone. Nothing in the app reads them back; they
  are presentation only. No behaviour keys off them.
- A reader stylesheet overriding `.rt-color-*` wins because the EPUB rules are single-class
  selectors with no `!important`.
- The colour dropdown items show the swatch *and* the colour name, like the callout
  dropdown shows an icon and a label.
- `title`/`aria-label` come from the `title` key each item already carries.
