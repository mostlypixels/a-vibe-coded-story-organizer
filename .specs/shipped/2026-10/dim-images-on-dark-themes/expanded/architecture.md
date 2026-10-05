# Dim images on dark themes — architecture

No migration, route, controller, or policy change. Config, one value object, one renderer, one CSS rule.

## Opacity is not a colour token

The theme vocabulary is colour-only. An opacity fits none of these rules:

- `ThemeTokens::ALL` emits `--color-<token>`.
- `ThemeStyleBlock::declarations()` drops any value that fails `Oklch::CSS_VALUE_PATTERN`.
- `ThemePresetTest` requires every token to sit in `PAIRS` or `DECORATIVE`.
- `ThemeRenderingTest::assertEmitsEveryToken` expects `--color-<token>:`.

**Decision:** a scalar key on the preset, beside `contrast_ceiling`. Not a `ThemeTokens` entry.

- Rejected: a `ThemeTokens` entry. It emits `--color-image-opacity`, needs a `DECORATIVE` exemption, and puts a second value whitelist inside the colour path.

## Config and value object

`config/themes.php`, per preset:

```php
'image_opacity' => 0.8, // daylight 1.0, dusk 1.0, low-glare-dark 0.8, no-halation 0.65
```

- `ThemePreset`: add `float $imageOpacity`. `fromSlug()` reads `$preset['image_opacity'] ?? 1.0`, so a missing key never breaks a page.
- A test requires every shipped preset to set the key explicitly (see testing).
- Add one line to the `contrast` comment block in `config/themes.php` saying what the key does.

## Rendering

`ThemeStyleBlock::declarations()` appends one entry after the colour loop:

- Key `--image-opacity`.
- Value: `(string) round($preset->imageOpacity, 2)`. `1.0` prints `1`, `0.8` prints `0.8`.
- Emit only when the float is within 0 to 1. Otherwise skip it. The CSS fallback below gives 1.
- Update the docblock: the array is no longer only `--color-<token>`.

`render()` and the Appearance live preview both read `declarations()`:

- `resources/js/font-preview.js` applies every entry with `setProperty`, so **no JS change**.
- `AppearanceController` already builds `$themeDeclarations` from it.

## CSS

One rule set in `resources/css/app.css`. The file has no `@layer` blocks, so add it as plain CSS like the rest. The only existing `img` rule is `.revision-diff--visual img` (sizing), which does not conflict.

```css
img { opacity: var(--image-opacity, 1); }
img:hover,
:is(a, button):is(:hover, :focus-visible) img { opacity: 1; }
img[data-full-opacity] { opacity: 1; }
```

- The `var()` fallback covers a layout without `x-theme-style` and a dropped value.
- Hover on an enclosing link or button counts. A card link restores its cover.
- Images are not focusable. Focus lives on the wrapping `<a>` or `<button>`, so the focus rule targets those.
- No transition. A fade adds a motion rule for no benefit.
- Opacity also dims the image border. Accept it.
- The rule is unlayered, so it outranks a Tailwind `opacity-*` utility on an `<img>`. No view does this today. Put the opacity on a wrapper if one ever needs it.

## Lightbox

The lightbox is inline markup at the bottom of `codex/partials/fields.blade.php`. The codex show page becomes its second caller, so extract it.

- New `resources/views/components/image-lightbox.blade.php`. Same markup and classes as today. It owns its Alpine state.
- It opens on a window event: `$dispatch('open-lightbox', { url, alt })`. This matches the `open-modal` pattern. No parent variable.
- It closes on Escape, scrim click, and the close button. Same look and behaviour as today, with no new focus trap.
- Not built on `x-modal`: that component adds a panel background and clipping, which change the bare-image look.
- The `<img>` carries `data-full-opacity` (see CSS).
- Edit page: the thumbnail buttons dispatch the event. Remove `lightbox` from the `x-data`. Leave `filePreview` alone.
- Show page: the main `<img>` in `codex/show.blade.php` moves inside a `<button type="button">` that dispatches the current image. Use the focus-ring classes of the edit-page thumbnail button. Thumbnails keep switching `current`. A one-image gallery gets the button too.
- Include the component once per page.

## Files to touch

- `config/themes.php`
- `app/Support/ThemePreset.php`
- `app/Services/ThemeStyleBlock.php`
- `resources/css/app.css`
- `resources/views/components/image-lightbox.blade.php` (new)
- `resources/views/codex/partials/fields.blade.php`
- `resources/views/codex/show.blade.php`
- `documentation/interface/themes.md`: Presets section (the scalar key), "Adding a preset" step, and a short "Image dimming" section.
- `CHANGELOG.md`: one dated section for the pull request.

> [!WARNING]
> `ThemeStyleBlock` prints its output unescaped. The new value must come only from the validated float, never from a raw config string.
