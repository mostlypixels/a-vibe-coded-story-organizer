# Theme Switcher — open questions

> [!IMPORTANT]
> **Resolved in the grill (2026-08-01). This file is now history — `plan/00-overview.md` is
> binding.** Read it for the reasoning behind a decision, not to reopen one. Deltas from the
> recommendations below:
>
> - **Q1** — per-user, not a global singleton. `users.theme_slug` nullable →
>   `config('themes.default')`; no settings table at all. There are no guests past login except
>   the share page, which takes the default. The picker lives at `/admin/appearance`, where the
>   Configuration area already renders per-user data.
> - **Q2 + Q6** — one decision. `/` keeps its route but is stripped to app name + a themed login
>   button; registration is already disabled, so the register block was dead anyway. That
>   removes the last `dark:` classes and all 20 arbitrary hexes.
> - **Q3** — the `nav` family ships. **Q4** — `link` / `link-hover` ship.
> - **Q10** — `indigo` → `accent-surface`/`accent-content`, `gray` → `neutral`. `indigo` must
>   stay distinct from `info`: `revision-origin-badge` uses both, for `Import` and `Manual`.
> - **Q11** — six sweep tasks, not three PRs; see `plan/00-overview.md`. **Q12** — three presets.
> - **Q5** (`prefers-color-scheme`) and Q8's sub-question stand as recommended: no, and clamp.

## Q1 — Per-user or global? **Recommend: global singleton now.**

`spec.md` says per-user is obvious, then trips over the login page having no user.

- Every other setting in this app is a global singleton (`CrawlerSetting`, `RevisionSetting`,
  `ImportSetting`), the Configuration area already has an *Appearance & accessibility* section
  behind `access-admin`, and `access-admin` is "any authenticated user" — so global costs one
  one-column table and no new concepts.
- Guest and public layouts read the same singleton. No cookie, no guest default, no third code
  path for `layouts/public`.

**Cost of being wrong:** if per-user is wanted *now*, the singleton becomes the fallback rather
than dead code, and `x-theme-style` picks `auth()->user()?->theme ?? ThemeSetting::current()`.
Small. That asymmetry is the argument — and it does not require pre-building a `themes` table
to collect the benefit.

## Q2 — Does `dark:` survive? **Recommend: no. Delete the axis. Same decision as Q6.**

Measured: 35 authored `dark:` classes exist in `resources/`, and **all 35 are in one file** —
`welcome.blade.php`, Laravel's stock splash page. No component, layout, or app page uses the
variant. Every one of them also pairs with a hard-coded hex (`dark:bg-[#0a0a0a]`) that no
token could reach regardless.

So Q2 and Q6 are not two questions: rewriting `welcome` (Q6) is what removes the `dark:`
variant from the codebase. Decide them together. Do not add `@custom-variant dark`; dark mode
is the `low-glare-dark` theme.

## Q3 — The nav bar is not `surface-raised`. **Recommend: add `nav` / `nav-content` / `nav-raised`.**

`layouts/navigation` is `bg-navy-950` with a `bg-ocean-900` project picker and `text-aqua-100`
links, above a `bg-gray-100` page. It is a dark band inverting the page, not an elevated
surface. Options:

1. **Add the `nav` family** (recommended) — Daylight stays pixel-stable, and the dark band is
   an explicit design decision a theme can honour or drop.
2. Force it to `surface-raised` — loses the current look immediately, and pre-V1 that may be
   acceptable. Say so out loud if so; it is a visual redesign, not a rename.
3. Give the nav its own inverted sub-scope. Rejected: a second axis, which is how theme systems
   rot.

## Q4 — Are links `primary`? **Recommend: no — add `link` / `link-hover`.**

`x-button variant=primary` is `bg-navy-900`; links are `text-ocean-600 hover:text-ocean-800`
(61 usages). Collapsing them makes every link the button color on day one. Alternative: call
the link role `accent` and find another name for the nav indicator — worse, `accent` is already
spoken for.

## Q5 — Does `prefers-color-scheme` pick a default? **Recommend: no.**

One active theme, chosen explicitly. Honouring the media query means the rendered `:root` block
depends on client state the server cannot see, which means either a `@media` wrapper around two
themes (double the payload, and the ceiling/floor guarantees stop being about *one* theme) or
client-side selection (FOUC, the thing the inline block exists to avoid). Revisit in spec 3,
where per-user storage makes "follow my system" a real preference rather than a guess.

## Q6 — What happens to `welcome.blade.php`? **Recommend: rewrite its markup. Decide with Q2.**

It will not respond to `:root` overrides — but not for the reason it first appears. Its inlined
`<style>` is an `@else` fallback for builds that do not exist yet; with a build present the page
loads `app.css` like everything else. The actual blocker is ~20 **arbitrary-value hexes** in the
markup (`bg-[#FDFDFC]`, `text-[#706f6c]`, `dark:bg-[#0a0a0a]`), which no token can reach.

Rewrite the markup against role tokens (275 lines, mostly splash — and it carries all 35
`dark:` classes, so this is Q2's implementation), or exclude it explicitly and record that in
`standing-issues.md`. Not deciding is how it ends up half-wired.

## Q7 — Daylight: literal values or generated ramps? **Recommend: literal now, regularize later.**

Pixel-stability and OKLCH-generated ramps are in direct conflict — the current hexes are not on
an even ramp, which is the whole complaint about them. Shipping Daylight as literal values keeps
the computed-style diff meaningful and makes the sweep reviewable. Regularizing it is a
follow-up with its own browser pass. The alternative — accept a visual change to the default
theme in the same PR as a 900-usage rename — makes the diff unreadable.

## Q8 — Contrast ceiling: whose opinion is it? **Recommend: per-preset in config, warning-level.**

Halation is one reason to cap contrast, not the only reason to have an opinion about the top of
the band, and different conditions pull in opposite directions — a preset for low vision may
want a higher ceiling than one for astigmatism. So the ceiling must vary per preset. But it is
**not a database column**: nothing reads it while rendering, only the person picking anchor
colors and the test guarding them, and both read config. Settled:

- floors (4.5 / 3.0) are WCAG, global, and **reject**;
- the ceiling is declared per preset in `config/themes.php`, defaults to
  `config('themes.contrast.default_ceiling')`, and **warns**;
- `ColorContrast::verdict()` takes it as an argument so no constant returns.

Open sub-question: should a preset be able to declare a ceiling *below* the 4.5 text floor
(a deliberately soft theme)? Recommend no — floors win, and the config value is clamped.

## Q9 — What is `sun-400`? **Recommend: a `table-header` surface, not `highlight`.**

`spec.md` reads the two `sun` usages as "search-result and table-row highlights". They are not.
`sun-200` is the search `<mark>` (from `SearchSnippet::HIGHLIGHT_CLASS`); **`sun-400` is
`x-table`'s `<thead>` band** — the header of every table in the app. `SearchSnippet`'s own
comment says so: *"`bg-sun-400` is already the table-header color"*. There is no row highlight.

So: `highlight` / `highlight-content` is one token because it has one user, not because two
shades were merged. `sun-400` needs its own home — a `table-header` pair, or `surface-sunken`
if the browser pass says the tint is dispensable. Merging them would repaint every table header
in the search-highlight color.

## Q10 — `x-badge`'s hue-named variants. **Recommend: rename both, and pick a non-colliding name.**

`x-badge` already ships `info` / `success` / `warning` / `danger` / `primary`. The hue-named
ones are only **`indigo`** and **`gray`** — and `indigo` is not even indigo, it is
`bg-ocean-100 text-ocean-800`. So "rename to the status roles" is a no-op or a collision;
`indigo` needs a role that does not already exist on the component.

- `indigo` — one caller, `revision-origin-badge.blade.php` for `RevisionOrigin::Import`. It
  means "neutral-but-noteworthy". `accent` is the closest existing token.
- `gray` — also the `@props` default, so renaming it touches every callsite that omits
  `variant`. `neutral` is the obvious name; decide whether the default is renamed or just
  re-valued.
- `app.css`'s `.diff-note` is documented as kept in step with the `info` variant — move both.

## Q11 — Is the ~900-usage sweep one PR? **Recommend: split at the mechanism boundary.**

`spec.md` scoped 286 usages; the real number including `gray-*`, `-white` and status hues is
about 900. Suggested split:

1. mechanism + tokens + Daylight + component library (`components/`),
2. layouts + pages,
3. Dusk + Low-glare dark + the Appearance form.

Each is independently green and independently reviewable. One 900-line-diff PR is not.

`master` is protected and needs a green `tests` check per PR, so `NoHueNamedColorsTest` cannot
sit red across the sweep — it ships with a shrinking allow-list of unswept paths (see
`testing.md`).

## Q12 — Three presets or four? **Recommend: three.**

`spec.md` says "3–4". Daylight, Dusk, Low-glare dark. The fourth would be a high-contrast
preset — genuinely valuable, and the per-preset ceiling (Q8) exists partly for it — but it is
the one preset whose value depends on user feedback this project does not have yet. Ship three,
and let the fourth be the first thing spec 3's picker proves is easy.
