# Theme Switcher — plan overview

The manual for this feature's tasks. Never implemented, never moved to `implemented/`.

Replace hue-named colors with paired role tokens, emit them as `:root` custom properties a
user can switch at runtime, and ship three presets — one of them a genuine low-glare dark
theme. ~900 class usages move.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `color-primitives` | `Oklch` + `ColorContrast` in `app/Support`, unit-tested. Nothing renders. |
| 02 | `token-vocabulary-and-style-block` | `ThemeTokens`, `config/themes.php` with Daylight only, `ThemePreset`, `ThemeStyleBlock`, `<x-theme-style />` in every layout. Old ramps still present. |
| 03 | `theme-ramp-command` | `php artisan theme:ramp` + a **rough** low-glare-dark preset, so later tasks have something to switch to. Also `Oklch::fromCss()` and the two vocabulary amendments below. |
| 04 | `user-theme-preference` | `users.theme_slug` + resolution + the picker on `/admin/appearance`. |
| 05 | `sweep-components-chrome` | ~140 usages: button, badge, card, table, alert, heading, breadcrumbs, dropdown, modal, popover, dialog. Introduces `NoHueNamedColorsTest`. |
| 06 | `sweep-components-forms-nav` | ~120 usages: form controls, pickers, `autosave-*`, `revision-*`, `icon-*`, nav/sidebar links. |
| 07 | `sweep-layouts` | 36 usages: `layouts/app`, `guest`, `public`, `navigation`. |
| 08 | `sweep-admin-codex` | 211 usages across `views/admin` and `views/codex`. |
| 09 | `sweep-remaining-pages` | ~250 usages across 13 small page folders. |
| 10 | `sweep-css-and-js` | `app.css`'s 42 hand-written hue references + `resources/js/autosave/badge.js`. |
| 11 | `delete-legacy-ramps` | Remove the five ramps, the border shim, `--color-nav-active`. Allow-list empty. Computed-style diff vs `master`. |
| 12 | `final-presets` | Re-author Dusk + Low-glare dark, **and Daylight's six failing values**; contrast matrix test across all three; browser pass. |
| 13 | `landing-page` | Strip `/` to app name + themed login button. |
| 14 | `documentation` | `architecture.md` *Theming* section, `documentation/theming.md`, CHANGELOG. |

Tasks 05–10 are the sweep. Each is verified identically: feature tests green,
`BladeComponentCompilationTest` green, `NoHueNamedColorsTest`'s allow-list one path shorter,
and a look at the swept screens under the dark preset.

## Binding decisions — do not re-litigate

Settled in the grill. A task that disagrees should stop and raise it, not quietly diverge.

**Storage**
- `users.theme_slug`, nullable. `null` means "follow the default", so changing the default
  still reaches everyone who never picked.
- The default is `config('themes.default')`. **No `themes` table, no settings singleton, no
  seeder.** Presets are config: token values, display name, optional `contrast_ceiling`.
- Unauthenticated pages (`/shared/scenes/{token}`, login, `/`) use the config default.

**Where the picker lives**
- `/admin/appearance`, replacing the existing placeholder. The Configuration area already
  renders per-user data (`DataTransferController` scopes every list to `$request->user()`).
- The form writes to `$request->user()`, so there is no cross-user authorization case to test.
  `authorize()` is `$this->user() !== null`, matching `UpdateCrawlerSettingRequest`.

**Vocabulary** — ~32 flat tokens. No shade suffixes: `bg-primary`, never `bg-primary-600`.
- `nav` / `nav-raised` / `nav-content` / `nav-content-muted` — the nav bar is a dark band above
  a light page, not `surface-raised`.
- `link` / `link-hover` — separate from `primary`, which is `x-button`'s fill.
- `focus` — its own token, never an alias of `primary`.
- `table-header` — `x-table`'s `<thead>`. `highlight` is the search `<mark>` alone.
- `x-badge`: `indigo` → `accent-surface`/`accent-content`, `gray` → `neutral`.
- **No `dark:` variant.** Do not add `@custom-variant dark`. Dark mode is a preset.

**Presets** — exactly three. Daylight starts as the **current literal values**, so the sweep
cannot change how the app looks; task 12 then re-authors the values that fail contrast. Dusk
and Low-glare dark are `theme:ramp` output pasted into config.

**Status tokens are four, not three** — `<status>`, `<status>-content`, `<status>-surface`,
`<status>-surface-content`. A trio forces tint text onto `<status>` itself, which measures
1.85–4.82 against the tints: three of the four statuses land below the text floor, warning
worst. The fourth token also lets Daylight keep today's `X-800` tint text verbatim, so the
sweep stays rename-only.

**Contrast** — floors 4.5 (text pairs) / 3.0 (`accent`, `border-strong`, `focus`) are global
and reject. The ceiling is per-preset, defaults from config, and warns. A preset may not
declare a ceiling below the text floor; clamp it.

**`border` is decorative and carries no floor.** A hairline between two table rows is not a UI
component under WCAG 1.4.11, and forcing 3:1 would take it from `#e5e7eb` to `#8a8c90` — every
card edge and divider in the app as a mid-grey line. `border-strong` and `focus` keep the 3:1
floor, because those do identify controls and state. `border` stays in `PAIRS` (the vocabulary
must stay complete) and is listed in `ThemeTokens::DECORATIVE`, which the matrix skips.

**Accessibility outranks pixel-stability.** Where the two conflict, Daylight changes. Task 12
owns every such change; see the list in that task file.

**CSS**
- Plain `@theme`, never `@theme inline` — `inline` bakes values into utility rules and silently
  breaks every override.
- Never wrap `<x-theme-style />`'s block in `@layer`. An unlayered `:root` rule is what
  outranks Tailwind's `@layer theme`; layering it loses.
- No `rgb(var(--x) / <alpha-value>)` channel-triplet workaround. v4 compiles `bg-primary/50`
  to `color-mix()` on its own.
- No cache on the rendered block. Default store is `database`; caching would trade ~30
  `sprintf` calls for a SQL round-trip per render.

## Invariants every task must preserve

- **Every token that names a background has a foreground partner**, and they are chosen
  together. This is the whole point: an unreadable combination must be unrepresentable.
- **The sweep is rename-only; task 12 is the only task licensed to move a pixel.** Tasks 05–11
  must leave Daylight rendering identically to `master`, and task 11's computed-style diff
  proves it. Task 12 then re-authors Daylight's failing values deliberately. A visual change
  before task 12 is a bug in the rename.
- **Focus affordances never regress.** Every `focus:ring-*` lands on `focus`, not `primary`.
  Spec 1 already had to restore a ring lost this way; do not lose another.
- **`ThemeStyleBlock` emits unescaped CSS**, so it whitelist-validates every value it renders.
  That guarantee stands on its own, independent of spec 3.
- Authorization: the Appearance form is behind `auth` + `access-admin` and writes only to the
  acting user. It does **not** use `ProjectPolicy`'s walk — it is owned by no `Project`.

## Deferred, deliberately

Note in `resolution-log.md` if a task's work makes one of these urgent; do not decide alone.

- `prefers-color-scheme` as a "follow my system" choice — belongs with spec 3's per-user work.
- `surface-raised` and `surface-overlay` are the same value in Daylight (everything is
  `bg-white` today). Distinctness is asserted on generated presets only.
- Regularizing Daylight onto even OKLCH ramps — a follow-up with its own browser pass.
- `navigation/dropdown-trigger` has no focus ring (pre-existing, from spec 1). Task 06 touches
  the file; closing it there is cheap and in scope.
