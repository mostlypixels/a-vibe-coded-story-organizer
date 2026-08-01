# Theme Switcher — overview

## Problem

Colors are named by hue (`ocean`, `navy`, `aqua`, `sun`). A hue name says what a color *is*,
never what it is *for*, so no theme can be swapped in without every class becoming a lie. The
existing ramps were eyeballed in sRGB, so steps are perceptually uneven and contrast is
accidental (`flame-500` sat at 2.48:1 on white).

## Goals

1. Replace hue-named colors with **role tokens that come in background/foreground pairs** —
   an unreadable combination becomes unrepresentable.
2. Generate ramps in **OKLCH** from an anchor, so lightness steps evenly and contrast is
   predictable.
3. Repaint the whole app from custom properties on `:root` — **no rebuild, no class swap, no
   `dark:` variant**.
4. Ship **three presets**, one of them a genuine low-glare dark theme, because a second theme
   is the only thing that proves the vocabulary holds.

## Non-goals

- Pickers, per-user themes, fonts, sizing → `display-configurator` (spec 3).
- The v4 port → `tailwind-4` (spec 1, shipped `2026-08`).
- Per-project or per-document theming — ever. Themes are a display preference.

## What spec 3 inherits from this one

Build these so spec 3 only has to write to them:

| Built here | Used there |
|---|---|
| `Oklch` + `ColorContrast` | the live contrast readout |
| `theme:ramp`'s lightness curve | the "basic tier" four-picks-the-rest, promoted to a class if the picker needs it live |
| `ThemeTokens` + `ThemePreset` | the shape a user's saved theme has to satisfy |
| `config('themes.contrast')` floors + per-preset ceilings | the "comfortable band" marker |
| `ThemeStyleBlock` renderer | repaints from whatever spec 3 saves |

Spec 3 adds the `themes` table — a row per user theme. It is **not** built here, because
nothing in this spec varies per row at runtime; see `data-model.md`.

## Scope correction — the spec's usage counts are stale

`spec.md` counts 286 usages (`ocean` 205, `navy` 40, `aqua` 29, `flame` 7, `sun` 2). That was
measured before the v4 port. On `master` today:

| Family | Blade usages | Note |
|---|---|---|
| `ocean` | 144 | 45 of them are `focus:ring`/`focus:border` → belong to `focus`, not `primary` |
| `navy` | 40 | includes `bg-navy-950` (the nav bar) and `text-navy-900` (body text) |
| `aqua` | 29 | almost entirely nav-bar foregrounds |
| `sun` | 2 | the search `<mark>` and **`x-table`'s header band** — not two highlights |
| `flame` | **0** | already collapsed into `--color-nav-active` by spec 1 |

**And the real number is roughly four times that.** "No hue-named color remains" cannot stop
at the custom families — the spec itself already assigns `gray-500`/`gray-700` →
`content-muted` and `gray-400` → `content-subtle`, and a dark preset with a light-grey page
background is not a dark preset. Also in Blade:

| Stock palette | Usages |
|---|---|
| `gray-*` | 462 in Blade, 484 counting `resources/js` and `app.css` (`gray-500` 127, `gray-700` 75, `gray-600` 66, `gray-200` 46, `gray-300` 38, …) |
| `-white` | 68 |
| status hues (`red` 33, `green` 28, `yellow` 23, `blue` 17, `amber` 11, `indigo` 11, `emerald` 11, `slate` 11) | 145 |

**Total migration surface: ~900 class usages.** Plan the work as a mechanical sweep driven by
a mapping table (`architecture.md` → *Migration map*), not as 286 individual judgement calls.
Budget it as the dominant cost of this spec.

## Two vocabulary gaps found in the code

Both would break pixel-stability of the Daylight preset if the token list ships as written.
Recommendations in `open-questions.md`.

- **The nav bar is dark on a light page.** `layouts/navigation` is `bg-navy-950` with an
  `bg-ocean-900` project picker and `text-aqua-100` links, sitting above a `bg-gray-100` page.
  It is not `surface-raised` — it inverts the page. `surface-raised` in the token table is
  claimed by cards (`bg-white`) and dropdowns.
- **Links are not `primary`.** `x-button variant=primary` is `bg-navy-900`; links are
  `text-ocean-600 hover:text-ocean-800` (61 usages). Two different roles wearing "the brand
  color".

## Acceptance criteria

- [ ] No `ocean` / `navy` / `aqua` / `sun` / `flame` / `gray` class or CSS variable in
      `resources/views`, `resources/js`, or `resources/css/app.css`.
- [ ] The `@layer base` border-color shim in `app.css` is **deleted**, its 88 width-only
      `border` usages now resolving through `--color-border` (spec 1's `standing-issues.md`
      names this spec as its removal).
- [ ] `--color-nav-active`'s fuchsia placeholder is gone, replaced by a real `accent` value
      that clears 3:1 on the nav bar.
- [ ] Generated presets come from `php artisan theme:ramp`, with contrast assertions per token
      type over every preset in config.
- [ ] Three presets seeded and selectable; switching repaints with no rebuild and no FOUC.
- [ ] Daylight renders pixel-identical to `master` (diff computed styles, as spec 1 did). This
      necessarily means some Daylight tokens share a value — `surface-raised` and
      `surface-overlay` are both `#fff` today. Distinctness is asserted on the generated
      presets only; see `testing.md`.
- [ ] Every page walked in a browser under **each** preset.
- [ ] `documentation/architecture.md` gains a *Theming* section linking
      `documentation/theming.md`.

## User stories

- *As a writer with astigmatism*, I pick **Low-glare dark** and body text is pale grey on dark
  grey, not white on black, so it does not halate.
- *As a writer in a bright room*, I keep **Daylight** and notice nothing changed.
- *As the next developer*, I read `bg-surface-raised` and know where it may be used without
  opening a color chart.
- *As spec 3's author*, I add a settings form and a JSON column already exists to write to.
