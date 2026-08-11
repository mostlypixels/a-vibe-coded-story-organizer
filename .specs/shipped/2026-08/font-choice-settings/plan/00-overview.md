---
title: Font Choice Settings — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-font-config-and-choice.md` | `config/fonts.php` (the whole vocabulary) + `App\Support\FontChoice::resolve()`. Nothing renders yet. |
| 02 | `02-font-files-and-face.md` | `scripts/fetch-fonts.sh`, the woff2 files, `@font-face` blocks, `--font-manuscript` `@theme static`. Depends on 01 (the config is what the drift test checks against). |
| 03 | `03-user-columns.md` | Migration: five nullable columns + `User::$fillable`. Independent of 01/02. |
| 04 | `04-style-block.md` | `App\Services\FontStyleBlock` + wiring into the existing `x-theme-style`. Depends on 01, 03. |
| 05 | `05-prose-surfaces.md` | `rich-text`, `wysiwyg` editable area, `.prose` leading/scale consume the variables. Depends on 04. |
| 06 | `06-request-and-controller.md` | `UpdateThemeSettingRequest` → `UpdateAppearanceRequest`, rules, `edit()`/`update()`. Depends on 01, 03. |
| 07 | `07-picker-ui.md` | The radio cards, per-family labels in their own face, the manuscript sample. Depends on 06. |
| 08 | `08-live-preview.md` | Alpine live preview writing `documentElement.style` from a server-rendered slug map. Depends on 07. |
| 09 | `09-documentation.md` | `documentation/architecture.md` section + `documentation/fonts.md`. Depends on all. |

Task 02 is the only network-dependent task and is deliberately isolated: a CDN failure
blocks nothing until 04 renders a stack.

## Binding design decisions (do not re-litigate)

All resolved via grilling — full record in `../resolution-log.md`.

1. **`config/fonts.php`, not `app/Enums/FontFamily`.** Supersedes `spec.md`. An enum
   cannot hold `stack`/`note`/`bundled` without three parallel `match` expressions, and
   `config/themes.php` already established config as the home for authored slug data.
   Validation is `Rule::in(array_keys(config('fonts.<list>')))`.
2. **Five bundled families** — Inter, Atkinson, Lexend, Literata, Source Serif 4 — plus
   four `bundled => false` ones (Arial, Verdana, Georgia, system stack).
3. **Two scale settings, not one.** `ui_scale` and `manuscript_scale` are separate
   columns and separate fieldsets. Deviation from `expanded/architecture.md`, which had
   a single `text_scale`.
4. **Manuscript scale is relative and multiplies.** `ui_scale` sets `:root{font-size}`;
   `manuscript_scale` is a percentage on `.prose`, so the two compose. Its steps read as
   *same / larger / largest*, not as absolute sizes.
5. **Inter becomes the default, with no data backfill.** `null` stays "follow config",
   so `config('fonts.default_ui')` remains reachable. The author picks Atkinson once.
6. **Live preview via Alpine**, resolving slugs through a **server-rendered lookup map**
   — an unknown key is a no-op, never a written value. Supersedes `expanded/ui.md` and
   open question 3, which recommended apply-on-submit.
7. **The WYSIWYG editable area follows the manuscript face, size and leading**; its
   toolbar stays `font-sans` and the UI scale.
8. **`UpdateAppearanceRequest`** — one request, one form, one PATCH, six fields.
9. **Font files stay checked in**, fetched by `scripts/fetch-fonts.sh` from pinned
   fontsource CDN URLs. No `@fontsource` npm dependency; not Vite-bundled.

## Core invariants every task must preserve

* **Nothing interpolated into the `<style>` block is user input.** A slug indexes an
  authored config array; a slug not in that array never resolves. This is a stronger
  guarantee than `ThemeStyleBlock`'s "we validate the value" — do not copy that wording
  into the new class, it would misdescribe the mechanism.
* **The JS preview obeys the same rule.** `map[slug] ?? no-op` — never a value read from
  the DOM, never a value assembled in JS.
* **`null` means "follow config", always.** No task writes a default into a column, and
  a stored slug that no longer exists in config falls back rather than throws.
* **One `<style>` block, one component.** `x-theme-style` renders both the theme and the
  font rules; a second `<style>` tag in four layouts is the drift its existing comment
  warns about.
* **Guests and public surfaces render config defaults**, because they resolve from
  `auth()->user()` which is `null` there. No special-casing — and it is the regression
  this feature is most likely to introduce, so every rendering task asserts it.
* **Exports never follow the choice.** `resources/views/exports/epub/styles.css` and
  `exports/book/layout.blade.php` stay untouched.
* **Authorization is the documented exception**: the action writes only to
  `$request->user()`, so `authorize()` stays `$this->user() !== null`. Keep that
  docblock; there is no ProjectPolicy walk here and no non-owner case to test — assert
  instead that user A's PATCH leaves user B's columns untouched.
