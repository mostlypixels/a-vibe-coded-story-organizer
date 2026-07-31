---
status: draft
---

# Tailwind 4

Mechanical port of the build from Tailwind 3.4 to Tailwind 4. **No visual change is
intended** — that is the acceptance criterion, not a side note.

First of three specs:

1. **`tailwind-4`** (this one) — the port. Colors keep their current names and values.
2. **`theme-switcher`** — role-based token vocabulary, the 286-usage rename, OKLCH ramps,
   presets including a low-glare dark theme, and the `:root` override mechanism.
3. **`theme-configurator`** — user-facing pickers, ramp generation from anchors, contrast
   validation, fonts.

## Why the rename is not in this spec

* A port has a sharp pass/fail test: nothing looks different. Any drift is a bug. Folding
  in the rename destroys that signal — a wrong-looking card could be a v4 default or a
  misjudged role.
* **A token vocabulary cannot be validated against a single theme.** `surface` vs
  `surface-raised` collapsing into one value, or a missing `content-muted`, only shows up
  when a second theme exists. Renaming first means guessing, then re-touching all 286
  usages when the guess is wrong.

The rename ships alongside the dark theme that proves it.

## Scope

* Install `@tailwindcss/vite` (already in `package.json:13`, currently unused) and wire it
  into `vite.config.js`.
* Delete `postcss.config.js`. Drop `autoprefixer` and `postcss` as direct dependencies —
  v4 prefixes internally, and `postcss` survives as a transitive dep of Vite.
* Remove the duplicate nested Tailwind under `node_modules` that the unused plugin pulled in.
* `resources/css/app.css`: `@tailwind` directives → `@import "tailwindcss"`.
* `tailwind.config.js` → `@theme` in CSS. Same five names (`ocean`, `aqua`, `navy`, `sun`,
  `flame`), same hex values. Delete the config file.
* Plugins → `@plugin "@tailwindcss/forms"` / `@plugin "@tailwindcss/typography"` (both
  0.5.x are v4-compatible; they stay as dependencies).
* The `content` glob at `tailwind.config.js:13` becomes `@source`.
* Rewrite the **81** `theme('colors.…')` calls in `app.css` to `var(--color-…)`.

`npx @tailwindcss/upgrade` does most of the above. It is a starting point, not the deliverable.

> [!WARNING]
> Use `@theme`, never `@theme inline`. `inline` bakes values into the utility rules instead
> of referencing the custom properties, which silently breaks the runtime override spec 2
> depends on.

## Out of scope

* Semantic renaming (`ocean` → `primary` etc.) — spec 2.
* Regenerating ramps in OKLCH, chroma clamping, contrast fixes — spec 2.
* The dynamically generated `:root` stylesheet, DB storage, switching UI — spec 2.
* Pickers, fonts — spec 3.

## Visual drift — the real work

Tests stay green through all of this. They prove nothing here. Every page needs a browser
pass (`/run-imagoldfish`), because v4 changes defaults:

| Change | Affects |
|---|---|
| Default border/divide color `gray-200` → `currentColor` | every card, input, table |
| `ring` width 3px → 1px | 71 `focus:ring-ocean-500` usages |
| `shadow-sm` → `shadow-xs` (and `shadow` → `shadow-sm`) | cards, dropdowns, dialogs |
| `outline-none` → `outline-hidden` | focus states |
| Palette redefined in OKLCH/P3 | all `gray`/`red`/`green`/`blue` usages in `app.css` |
| Renamed opacity utilities | scattered |

> [!WARNING]
> Tiptap sets `prose` classes from JS (`resources/js/wysiwyg.js`). If the `@source`
> directive misses that file those utilities are purged and rich text renders unstyled —
> silently, with no build error.

## The one deliberate exception

`flame` is swapped for stock `fuchsia` — 7 border usages, all the active-navigation
indicator (`nav-link`, `responsive-nav-link`, `sidebar-link`,
`navigation/dropdown-trigger`).

Not a rename: a loud placeholder that makes the indicator announce itself during the visual
pass, so spec 2 starts knowing exactly where that token lands. Cheap to do here, and
`flame`'s current value fails contrast anyway (`#fb8500` on white is 2.48:1, under the 3:1
minimum for non-text UI).

Wire it through one variable rather than writing `fuchsia` into Blade, so spec 2 swaps a
single line instead of re-editing four components.

> [!NOTE]
> The `tab` variant of `sidebar-link` is the weakest active state — `border` plus
> `text-navy-900`, no background change. The border carries the signal alone. Worth a
> deliberate look during the visual pass.

## Consequences

* Browser floor rises to Safari 16.4 / Chrome 111 / Firefox 128 (v4 needs `@property`,
  `color-mix()`, cascade layers).
* Build gets faster; the PostCSS pipeline disappears.
* **Every theme token becomes a real CSS custom property at runtime.** This is the
  precondition spec 2 is built on: opacity modifiers compile to `color-mix()`, so a
  variable can hold any color format and the v3 `rgb(var(--x) / <alpha-value>)`
  channel-triplet workaround is never needed.

## Done when

* `npm run build` clean, `composer test` and `npm run test` green.
* `postcss.config.js` and `tailwind.config.js` are gone; `autoprefixer` and `postcss` are
  out of `package.json`.
* No `theme()` call remains in `app.css`.
* Every page walked in a browser against master, differences either fixed or recorded here
  as accepted.
* `documentation/ui-components.md` updated where the defaults it documents have moved.
