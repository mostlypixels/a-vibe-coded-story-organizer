# UI

## Trigger

In `layouts/navigation.blade.php`, a new block left of the account `x-dropdown`. Also left of the
mobile hamburger, because the icon is small and the need is the same on a phone.

- `x-dropdown align="right" :close-on-click="false"`, **no `hover`**. The panel holds form
  controls and loads over the network; hover would fetch on a pointer pass.
- Trigger: `x-disclosure-button` with `x-tabler-palette`, `aria-label="{{ __('Appearance') }}"`,
  the same `h-12` / `text-nav-content` / focus ring classes as the book menu button.
- Panel width about `w-72`.
- Before the first load the panel shows a short "Loading…" line, `aria-live="polite"`.

## Panel partial: `resources/views/appearance/switcher.blade.php`

No layout. Wrapped in `x-data="appearanceSwitcher({{ Js::from($config) }})"`. The config
carries the preview map, the ordered scale slugs, the active slugs and the `PATCH` URL.

Layout, top to bottom:

1. **Theme.** Heading, then a `fieldset` of swatches, `flex flex-wrap gap-2`.
2. **Interface font.** Family `<select name="ui_font">`, then two rows:
   - Size: `−` button, the current label (`16px`), `+` button.
   - Spacing: the same, label in `×` (`ui_leading`).
3. **Manuscript font.** The same, `name="manuscript_font"`. Both labels use `×`
   (`manuscript_scale`, `manuscript_leading`).
4. Error line, `role="alert"`, hidden when empty.
5. `x-dropdown-link` "More settings" → `admin.appearance.edit`.

## `x-theme-swatch` (new component)

- A radio, `name="theme_slug"`, `sr-only`, in a label. Native radio keyboard handling stays.
- Square about `h-8 w-8`, colours from `ThemePreset::swatch()`: the `surface` plate with the
  `primary` / `accent` / `focus` stripes, like `x-theme-card` in small.
- Checked: `ring-2 ring-link`. Focus: the same `peer-focus-visible:outline-*` as `x-theme-card`.
- Name: in `x-tooltip` for sight, and in an `sr-only` span for screen readers. A tooltip alone is
  not an accessible name.

## Font select

- Native `<select>`, the same classes as the locale select on the Appearance page.
- Each `<option>` sets `style="font-family: …"` from the config stack. Firefox shows it.
  Browsers that ignore it show the plain name. No custom listbox.
- No accessible-font eye icon here. The full picker keeps it.

## Size buttons

- `<button type="button">` with `x-tabler-minus` / `x-tabler-plus`, and an `aria-label` such as
  "Smaller interface text" or "Tighter manuscript spacing".
- Spacing rows use the same markup as size rows.
- `:disabled` at the ends of the slug list.
- The label between them has `aria-live="polite"`, so a screen reader hears the new size.

## Behaviour notes

- No button on `admin.appearance.edit`: a switcher change would make that form stale.
- No "Saved" toast. The repaint is the feedback. Only a failure shows text.
