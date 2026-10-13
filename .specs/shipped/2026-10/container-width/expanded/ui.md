# UI

## Appearance page — `resources/views/admin/appearance/edit.blade.php`

- Add one `x-setting-track` named `page_width`, legend "Page width", options from
  `PageWidth::steps()` with `format="width"`. Put it in a new "Layout" fieldset after the fonts.
- `x-setting-track` already renders the ordered track; it previews through `fontPreview`
  via the radio `change` event. Check that it accepts integer option keys.
- No sample box: the page itself is the preview.

## Quick switcher — `resources/views/appearance/switcher.blade.php`

- Add a `page_width` row with the existing − / + markup (`step()` / `canStep()`).
- Button labels: "Narrower page" / "Wider page". Row title: "Width".
- `AppearanceSwitcherController`: add `page_width` to `steps`, `labels` (`ScaleLabel` `width` format)
  and `active`. The row uses the existing `rows` loop; put it in its own section, not under a font.

## Behaviour

- The preview changes `--page-width` on `<html>`; the content reflows at once.
- On a phone the change has no visible effect (full width below `md`). Do not hide the
  control; a hint is not needed for now.
