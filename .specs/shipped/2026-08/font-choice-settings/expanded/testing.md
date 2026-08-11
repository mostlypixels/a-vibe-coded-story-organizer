# Testing

Extend the existing files rather than adding a parallel set — the feature is the second
axis of the same page.

## `tests/Feature/AppearanceSettingsTest.php`

- Guest is redirected from both routes (already covered; extend the PATCH payload).
- The picker lists every configured family, with the active one `checked` for each of the
  four fields.
- A user who never picked sees the **config defaults** marked active, for all four.
- A valid PATCH persists all four columns; `null` clears one back to the default.
- **Non-owner case does not exist here** — the action writes to `$request->user()`. Assert
  that instead: user A's PATCH leaves user B's columns untouched.

## `tests/Feature/ThemeRenderingTest.php`

- Every layout (app, guest, public share) emits `--font-sans` and `--font-manuscript`.
- A user with a stored `ui_font` gets that family's stack in the block.
- **The guest and public-share pages emit the config default even when a user with a
  different choice exists** — the regression this feature is most likely to introduce.

## New: `tests/Unit/Services/FontStyleBlockTest.php`

- A `null` field renders the config default.
- A slug removed from config renders the default rather than throwing (the stale-value
  case; mirrors `ThemePreset::resolve()`).
- An unknown scale/leading slug never reaches the output.
- The rule is unlayered and starts `:root{` — a guard against someone wrapping it in
  `@layer` later, which fails silently in the browser.

## New: `tests/Unit/FontConfigTest.php`

- Every family declares `name`, `stack`, `bundled`, `note`.
- Every `bundled => true` family has at least one matching `@font-face` block in
  `resources/css/app.css`, and every `@font-face` family name appears in the config —
  the same "two copies cannot drift" guard `ThemePresetTest` applies to the compiled
  theme block.
- Every `bundled => true` family's `src` files exist in `public/fonts/`. A missing woff2
  is invisible in dev (the stack falls through to the next family) and reaches production
  as "the font setting does nothing".
- The four config defaults are keys of their own lists.

## Validation

- A tampered `ui_font` / `text_scale` value fails with `assertSessionHasErrors` and leaves
  the column unchanged.
- Assert the rejected string never appears in a subsequent page render — the style-block
  guarantee, proved rather than asserted in a comment.
