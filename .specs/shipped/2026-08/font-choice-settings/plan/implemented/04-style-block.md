---
title: "Task 04 — Font style block"
---

# Task 04 — Font style block

## Scope

`App\Services\FontStyleBlock::render(FontChoice $choice): string`, and rendering it
inside the existing `x-theme-style` component so every layout picks it up.

The variables are emitted here; the prose surfaces that *consume*
`--font-manuscript`/`--manuscript-leading`/`--manuscript-scale` are task 05. `--font-sans`
takes effect app-wide immediately, which is the point.

## Depends on

01 (FontChoice), 03 (the columns to resolve from).

## Key decisions already made

* One unlayered rule, starting `:root{`, emitting: `--font-sans`, `--font-manuscript`,
  `--manuscript-leading`, `--manuscript-scale`, and `font-size` (the UI scale).
* `--font-sans` is the existing Tailwind theme variable, so every `font-sans` utility
  follows the UI choice with **no template change**.
* `font-size` on `:root` scales the whole app — Tailwind is rem-throughout, which is the
  point of the setting. Scaling only `.prose` was rejected: it leaves navigation, buttons
  and labels small for someone who asked for bigger text.
* `--manuscript-scale` is a percentage **relative** to that root, applied by task 05 on
  `.prose`; the two compose (overview decision 4).
* Same `{!! !!}` hazard as `ThemeStyleBlock`, **different and stronger guarantee**:
  nothing interpolated is user input at all — the slug indexes an authored config array,
  and an unconfigured slug never resolves. Say that in the class docblock; do not copy
  `ThemeStyleBlock`'s "we validate the value" wording, which would misdescribe this
  mechanism.
* Keep it **one component and one `<style>` tag**. `x-theme-style` renders both blocks.
* Resolution is from `auth()->user()` inside the component, exactly as the theme block
  does today — which is why guests get defaults with no special-casing.

## Consult

* `expanded/architecture.md` → *Rendering*
* `app/Services/ThemeStyleBlock.php` and
  `resources/views/components/theme-style.blade.php`

## Tests to add

`tests/Unit/Services/FontStyleBlockTest.php`:

* A `null` field renders the config default.
* A slug removed from config renders the default rather than throwing.
* An unknown scale/leading slug never reaches the output.
* The rule is unlayered and starts `:root{` — a guard against someone wrapping it in
  `@layer` later, which fails silently in the browser.

Extend `tests/Feature/ThemeRenderingTest.php`:

* Every layout (app, guest, public share) emits `--font-sans` and `--font-manuscript`.
* A user with a stored `ui_font` gets that family's stack.
* **Guest and public-share pages emit the config default even when a user with a
  different choice exists** — the regression this feature is most likely to introduce.
