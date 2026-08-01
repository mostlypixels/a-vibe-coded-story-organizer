# Overview — Tailwind 4

## Problem

The build is on Tailwind 3.4.19 through PostCSS. Tailwind 4 moves theme tokens out of a JS
config file and into CSS, where every token is a real custom property at runtime. That
property is the mechanism `theme-switcher` (spec 2) and `display-configurator` (spec 3) are
built on: without it, runtime theming needs the v3 `rgb(var(--x) / <alpha-value>)`
channel-triplet workaround, and `theme()` calls resolve at build time to a literal
`<alpha-value>` placeholder.

The port is also overdue on its own: `@tailwindcss/vite@^4.0.0` already sits in
`package.json:13` unused (a Laravel 12 skeleton artifact the Breeze-based project never
wired up), pulling a duplicate nested Tailwind 4.x into `node_modules`.

## Goal

Move the build to Tailwind 4 with **no perceptible visual change**. Color names and values
stay exactly as they are.

## Non-goals

| Deferred to | What |
|---|---|
| `theme-switcher` | role-named tokens, the 286-usage rename, OKLCH ramps, presets, `:root` override |
| `display-configurator` | pickers, contrast readout, fonts, sizing |

Also out: touching `admin/appearance` (an existing placeholder page — see
`open-questions.md`), any DB change, any new route, controller, policy or model. **This
feature adds no PHP.**

## Acceptance criteria

1. `npm run build` succeeds; `composer test` and `npm run test` stay green.
2. `postcss.config.js` and `tailwind.config.js` are deleted.
3. `autoprefixer` and `postcss` are gone from `package.json`; `@tailwindcss/vite` is a real
   dependency wired into `vite.config.js`.
4. No `theme()` call remains in `resources/css/app.css` (86 occurrences today).
5. Every page in the inventory (`ui.md`) walked in a browser against `master`; each
   difference either fixed or recorded in `standing-issues.md` as accepted.
6. `documentation/ui-components.md` and `documentation/architecture.md` updated where the
   defaults they describe have moved.

> [!WARNING]
> Criteria 1–4 are machine-checkable and will all pass while the app looks wrong. **Criterion
> 5 is the feature.** The test suite renders no CSS and asserts nothing about appearance;
> `npm run build` succeeds on a stylesheet with silently-dropped declarations. Treat a green
> pipeline here as evidence of nothing.

## User stories

There is no user-facing story. The honest framing:

- *As a developer*, I want theme tokens to be runtime CSS variables, so the theme switcher
  does not need a build step.
- *As a reader of this app*, I want nothing to change.

## Risks

| Risk | Mitigation |
|---|---|
| Visual drift from changed v4 defaults | Page-by-page browser pass (`ui.md`) |
| A `theme()` rewrite lands on a variable that does not exist → declaration silently dropped | Per-namespace mapping table (`architecture.md`); grep for `var(--` names not emitted by v4 |
| Auto source detection misses `vendor/` pagination views (gitignored) → pagination unstyled | Explicit `@source` (`architecture.md`) |
| Browser floor rises (Safari 16.4 / Chrome 111 / Firefox 128) | Accepted; see `open-questions.md` |

## Why this is one PR and not two

The port has a single sharp acceptance test — *nothing looks different* — and that test only
works while the diff contains nothing else. Splitting it further (deps in one PR, `theme()`
rewrites in another) leaves `master` in a half-migrated state where the test cannot be run at
all, since the stylesheet does not build. Ship it whole.
