---
title: "Task 02 — Font files and @font-face"
---

# Task 02 — Font files and `@font-face`

## Scope

Get the four new bundled families onto disk and into `resources/css/app.css`, and add
the `--font-manuscript` theme variable they will later be assigned to.

Does **not** change what any page renders — `--font-sans` keeps its current value until
task 04 overrides it at runtime.

## Depends on

01 (the config list is what the drift test compares against).

## Key decisions already made

* `scripts/fetch-fonts.sh` downloads from **pinned** fontsource CDN URLs into
  `public/fonts/`, matching the existing Atkinson filename convention. Files stay
  checked in; the script is provenance and a re-run path, not a build step. Follow
  `scripts/README.md`'s conventions for a new script.
* No `@fontsource` npm dependency, no Vite bundling — `spec.md` says "bundled through
  Vite", the shipped reality is checked-in woff2 + hand-written `@font-face`, and the
  shipped reality wins.
* **Variable** woff2 for the four new families: roman + italic, latin and latin-ext,
  one `@font-face` each with `font-weight: 200 700` and `font-display: swap`.
* Italic is load-bearing — fiction is full of it, and a family with no italic face gets
  a synthesised oblique. Atkinson keeps its current static set and stays without italic;
  that is a known cost of choosing it, and belongs in its config `note`.
* `--font-manuscript` is a new `@theme static` variable (fallback value: the Inter
  stack), giving a `font-manuscript` utility for task 05. `static`, never
  `@theme inline` — the existing comment block above `@theme static` explains why.
* `bundled => false` families get no `@font-face` and no file. That is the entire reason
  they are on the list.

## Consult

* `expanded/architecture.md` → *Font files* (and its warning: an unselected family's
  `@font-face` costs repo size, not page weight)
* `resources/css/app.css` — the existing Atkinson blocks and the `@theme static` comment

## Tests to add

Extend `tests/Unit/FontConfigTest.php` (task 01):

* Every `bundled => true` family has at least one matching `@font-face` block in
  `resources/css/app.css`, and every `@font-face` family name appears in the config —
  the two-copies-cannot-drift guard, same shape as `ThemePresetTest`'s.
* Every `src` file referenced by an `@font-face` block exists in `public/fonts/`. A
  missing woff2 is invisible in dev (the stack falls through) and reaches production as
  "the font setting does nothing".

Run `npm run build` once as well: a malformed `@font-face` fails there, not in PHPUnit.
