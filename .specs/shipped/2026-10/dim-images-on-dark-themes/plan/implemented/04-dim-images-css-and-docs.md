---
title: "Task 04 — Dim images CSS and docs"
---

# Task 04 — Dim images CSS and docs

## Scope

The change users see: images dim on dark presets, restore on hover and focus. Plus documentation, the changelog, and tuning by eye.

- `resources/css/app.css`: add plain CSS (the file has no `@layer` blocks):

  ```css
  img { opacity: var(--image-opacity, 1); }
  img:hover,
  :is(a, button):is(:hover, :focus-visible) img { opacity: 1; }
  img[data-full-opacity] { opacity: 1; }
  ```

- `documentation/interface/themes.md`: the scalar key in *Presets*, a step in *Adding a preset*, and a short *Image dimming* section. Keep it to bullets.
- `CHANGELOG.md`: one dated section for the pull request. Follow `.claude/rules/changelog.md`.
- Tune the two dark values by eye. Change `config/themes.php` and note any change in `resolution-log.md`.

## Depends on

Tasks 01, 02, and 03.

## Key decisions already made

- Dimming is on by default, including logged-out pages.
- No transition.
- The `var()` fallback of 1 covers a layout without `x-theme-style` and a dropped value.
- The rule is unlayered, so it outranks an `opacity-*` utility on an `<img>`. No view does this today.
- Opacity also dims the image border. Accept it.

Detail: `expanded/architecture.md` → *CSS*.

## Tests to add

- Run `npm run build`, then `bash scripts/verify.sh`. Run the documentation link test after the docs change.
- No PHP test for CSS. Do the manual check from `expanded/testing.md` → *Manual* with `run-imagoldfish` on `no-halation`, `low-glare-dark`, and `daylight`.
