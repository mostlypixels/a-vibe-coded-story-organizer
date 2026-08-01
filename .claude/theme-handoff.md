# Theme handoff

Tailwind 4 port shipped (`.specs/shipped/2026-08/tailwind-4/`, PR #69). The follow-up cleanup
pass is done too — form controls collapsed into components, two dead font families removed.

**Next: `theme-switcher` (spec 2), still a draft at `.specs/draft/theme-switcher/`.** Nothing
in the cleanup pre-empted its decisions: colour names, hex values and the `@theme` block are
untouched, and the border shim is still in place for it to remove.

## What the cleanup deliberately did *not* do

- **Left the `@theme` block alone** — 55 colour values, still `ocean`/`aqua`/`navy`/`sun`/`flame`.
  Renaming to role tokens is spec 2's call.
- **Left the border shim in place.** Fixing the 88 width-only `border` usages at the source
  now would mean writing `border-gray-200` at 88 sites that spec 2 immediately renames to
  `--color-border`. Churning them twice is worse than the shim.
- **Found nothing to sweep for v4 idiom.** The codemod's Blade output is clean (no
  `*-opacity-*`, no `flex-shrink`, two arbitrary values in the whole tree), and `app.css`'s
  hand-written rules already use `var(--color-*)`, `--spacing(N)`, `var(--radius-*)`.
  Task 04's audit did that work; there is no second pass owed.

## Read `resolution-log.md` first

Load-bearing findings that will otherwise be rediscovered:

- **v4 scans Markdown.** A class named in prose becomes a real rule; `.specs/` and
  `documentation/` are excluded via `@source not` in `app.css`. Any new docs folder needs the
  same, or the build ships phantom utilities.
- **`--radius-full` does not exist.** `rounded-full` is hard-coded `calc(infinity * 1px)`;
  spec 2 cannot make it themeable without inventing a variable.
- **The codemod ran twice** during the port and shifted 93 utilities one step down the v4
  scale. Caught by the browser pass, fixed. If shadows or radii look off, suspect this first.
- **A green suite proves nothing about CSS.** `composer test` renders none of it and
  `npm run build` succeeds on silently-dropped declarations. The guards that bite:
  `css-build.test.js` (dangling `var()`), `css-source-smoke.test.js` (`@source` reach), and
  now `FormControlComponentTest` (the shared form-control class string).
- **`standing-issues.md`** lists the accepted visual differences from v3 — don't "fix" them.

## Grep hygiene in this repo

Two files will silently poison any class-usage measurement. Exclude both, always:

- **`resources/views/welcome.blade.php`** carries ~40KB of inlined stock v4 CSS as its
  no-build fallback, so it contains the entire default Tailwind theme as literal text.
- **`public/build/`** is committed compiled output.

A `grep -r 'rounded-sm' resources/` without `--exclude=welcome.blade.php` reports the stock
scale, not this app's usage. It cost two wrong measurements during the cleanup pass.

## Verifying a change that touches rendered classes

The port's browser pass (computed styles across ~40 pages from a parallel `master` worktree)
is the heavy instrument; reach for it when CSS *values* change. For a refactor that only moves
class strings around, a rendered-HTML diff is faster and sharper:

1. `git worktree add ../imagoldfish-master master`, junction its `vendor` (`mklink /J`), copy `.env`.
2. A throwaway feature test in both trees GETs the affected routes and dumps each response body.
   **Seed faker** (`fake()->seed(...)`) and pin any `fake()->optional()` field, or the diff
   drowns in fixture noise.
3. Normalise (sort classes within each `class=""`, blank the CSRF token) and `diff -r`.

That found the form-control extraction rendering-identical across 16 pages — the only residual
difference was a timestamp one minute apart between the two runs.

**If you do run the browser pass:** `public/hot` must be absent and the dev containers stopped;
they have restarted themselves mid-run and re-created it.
