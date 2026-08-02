# Theme Switcher — architecture

## Support classes (`app/Support`)

Two small classes with real, testable contracts, following `PlotlineColors` / `WordCounter` in
placement and shape — plus `ThemePreset` (`data-model.md`). Ramp generation is deliberately
**not** among them; see below.

### `Oklch` — value object

`readonly class Oklch { public function __construct(float $l, float $c, float $h) }`

- `fromHex(string $hex): self` / `toHex(): string` / `__toString(): 'oklch(0.62 0.11 220)'`
- `withLightness(float $l): self`, `withChroma(float $c): self` — immutable steps
- `relativeLuminance(): float` — WCAG luminance, which is defined on **sRGB**, so this needs
  a real OKLCH → linear sRGB → sRGB conversion. Do not approximate with `L`; OKLCH lightness
  and WCAG luminance are different quantities and the ratios come out wrong.
- Gamut clipping: values outside sRGB clamp chroma down until in gamut, then convert. State
  which strategy is used in the class docblock — the naive per-channel clamp shifts hue.

### `theme:ramp` — an authoring command, not application code

Ramp generation has no runtime caller in this spec: presets store final values, and nothing
computes a ramp while serving a request. So it ships as
`app/Console/Commands/ThemeRampCommand.php` — anchor in, config-ready token values out, pasted
into `config/themes.php` by hand.

```
php artisan theme:ramp "#219ebc" --max-chroma=0.12
```

- `--max-chroma` clamps accents for dark presets (~0.12). Saturated color at high lightness is
  what makes a dark theme painful to look at; trivial to enforce here, impossible in hex.
- Print the contrast verdict for each shade alongside it — the command is where a human decides,
  so it is where the numbers belong.
- Spec 3 promotes the lightness-curve logic into a support class **if** its live picker needs
  it. Until then this is a script with a taste judgement encoded in it, and `app/Support` would
  be a lie about its role. (CLAUDE.md: check `scripts/`/commands before inventing a service.)

### `ColorContrast` — measurement

`ratio(Oklch|string $a, Oklch|string $b): float`, plus:

```php
ColorContrast::verdict(float $ratio, string $contrastClass, float $ceiling): Verdict
```

Returns `too_low` / `ok` / `too_high` against `ThemeTokens`' per-token contrast class and
**the ceiling it is handed**, never a constant. Floors are WCAG minimums and global (4.5 text,
3.0 non-text); the ceiling is declared per preset in `config/themes.php` and produces a
**warning**, never a rejection — see `data-model.md` → *Contrast thresholds*. Spec 3's live
readout calls exactly this.

## Rendering — `ThemeStyleBlock` + `<x-theme-style />`

`app/Services/ThemeStyleBlock.php` (a workflow, not reference data): takes a `ThemePreset`,
returns the `<style>` body — one `:root { --color-…: …; }` rule.

- **It emits unescaped CSS, therefore it validates.** Whitelist each value against a strict
  hex / `oklch()` pattern inside the renderer and drop anything that fails. That reason stands
  on its own — the `{!! !!}` in the component is the argument. It happens to also be the
  backstop behind spec 3's `ValidColor`, but do not write it down that way: a guarantee
  justified by a spec that might be cancelled is a guarantee someone will delete.
- **No cache.** `spec.md` says "cache the rendered block; bust on theme change", but the
  default cache store is `database` (`CACHE_STORE=database`), so caching trades ~30 `sprintf`
  calls for a SQL round-trip on every page render — almost certainly a pessimization. And a
  `rememberForever` keyed on `updated_at` never evicts: every save orphans a TTL-less row.
  Render it inline. If profiling later says otherwise, key on the slug and `forget` on save.
  Ramps are not recomputed per request either way — preset values are stored, not derived
  (see `data-model.md`).

`resources/views/components/theme-style.blade.php` emits it. Modeled on `x-robots-meta`: the
markup exists in exactly one file and every layout includes it, so the four layouts cannot
drift apart.

Wiring — in the head of all four, after `@vite(...)`:

> [!NOTE]
> Source order is **not** what makes the override win. v4 emits `@theme` into
> `@layer theme { :root { … } }`, and an unlayered `:root` rule outranks any cascade layer
> regardless of order (CSS Cascade 5). Placing the block after the stylesheet is convention,
> not mechanism. The real fragility is the opposite: wrap this block in `@layer` and it
> silently loses. Say so in the component's comment.


All four resolve the same way — `auth()->user()?->theme_slug ?? config('themes.default')`:

| Layout | In practice |
|---|---|
| `layouts/app` | the acting user's preference |
| `layouts/guest` | config default (login; no user yet) |
| `layouts/public` | config default — a stranger with a share link gets the neutral look |
| `welcome` | config default, or the user's if signed in |

> [!WARNING]
> `resources/views/welcome.blade.php` is Laravel's stock page and will not respond to `:root`
> overrides — not because of its stylesheet (the inlined `<style>` is an `@else` fallback for
> when no build exists; with a build present the page loads `app.css` normally), but because
> its markup is ~20 **hard-coded arbitrary hexes** — `bg-[#FDFDFC]`, `dark:bg-[#0a0a0a]`,
> `text-[#706f6c]`. No token can reach an arbitrary value. Rewrite the page against the app
> stylesheet (275 lines, mostly splash) or exclude it from theming explicitly. Do not leave it
> half-wired.

### FOUC and page-load

No extra request, ~1KB inline, parsed before first paint. Nothing to do beyond ordering the
block after the stylesheet.

## CSS — `resources/css/app.css`

- `@theme { --color-surface: …; --color-content: …; }` — **plain `@theme`, never
  `@theme inline`.** `inline` bakes values into utility rules instead of referencing the
  custom properties, silently breaking every runtime override. This is the single failure
  mode that produces "the switcher does nothing" with no error.
- v4 tree-shakes theme variables it sees no utility for. A token referenced only from the
  override block can therefore vanish from the compiled `:root`. If any token is not used by
  a generated utility, declare the block `@theme static` so all variables are emitted.
- **Delete** the `@layer base` border-color shim. Its own comment names this spec as its
  removal; `--color-border` replaces it, and the 88 width-only `border` usages resolve
  through the token instead of `gray-200`.
- **Delete** the five `--color-{ocean,aqua,navy,sun,flame}-*` ramps and `--color-nav-active`.
- Opacity modifiers need nothing: v4 compiles `bg-primary/50` to `color-mix(in oklab,
  var(--color-primary) 50%, transparent)`. The v3 `rgb(var(--x) / <alpha-value>)`
  channel-triplet workaround **must not** be reintroduced.

## `dark:` — the variant is dropped

Answering `spec.md`'s open question with a measurement: all 35 authored `dark:` classes in
`resources/` are in **one file**, `welcome.blade.php`, and every one of them pairs with a
hard-coded hex the theme cannot reach anyway. No component, layout, or app page uses the
variant. So dropping it costs exactly one page — the page the warning above already says to
rewrite. Do not add `@custom-variant dark`; dark mode is the `low-glare-dark` theme. One axis.

## HTTP layer

Extend the existing Configuration section rather than adding a new one — `AppearanceController`
and `admin.appearance.edit` already exist as a placeholder, and `AdminNavigation` already lists
*Appearance & accessibility*.

| Route | Action |
|---|---|
| `GET /admin/appearance` → `admin.appearance.edit` | exists; now passes `$themes` + `$active` |
| `PATCH /admin/appearance` → `admin.appearance.update` | new |

- `AppearanceController::update(UpdateThemeSettingRequest $request)` — `$request->user()->update(...)`
  → redirect with `->with('status', 'theme-updated')`. Thin, mirroring `GeneralSettingsController`.
- `app/Http/Requests/UpdateThemeSettingRequest`:
  - `authorize()`: `$this->user() !== null` — same shape and reason as
    `UpdateCrawlerSettingRequest` and `UpdateImportSettingRequest`. **No `ProjectPolicy` walk**:
    this is owned by no `Project`. Since the action writes to `$request->user()`, there is no
    cross-user case to guard at all.
  - `rules()`: `theme_slug` → `['nullable', Rule::in(array_keys(config('themes.presets')))]`.
    Never a free string reaching the renderer; `null` resets to the default.

The Configuration area already renders per-user data — `DataTransferController` scopes all
three of its lists to `$request->user()` — so a per-user preference is at home there.
- Both routes stay inside the existing `auth` + `access-admin` admin group.

No policy class. No new gate.

## Migration map

Sweep with this table; treat anything not on it as a judgement call to record. Left column is
`master` today, right is the token. Daylight's values make every row a no-op visually.

### Surfaces and content

| Today | Token |
|---|---|
| `bg-gray-100` (page shell, all four layouts) | `bg-surface` |
| `bg-white` (cards, panels, dropdowns) | `bg-surface-raised` |
| `bg-gray-50` (stripes, wells, file buttons) | `bg-surface-sunken` |
| modal/popover panels | `bg-surface-overlay` |
| `text-navy-900` (22) | `text-content` |
| `text-gray-500` (123) / `-600` (64) / `-700` (74) | `text-content-muted` |
| `text-gray-400` (26) | `text-content-subtle` |
| `border-gray-200` (34, plus the base shim) | `border-border` |
| `border-gray-300` (31 — inputs, table dividers; the rest of `gray-300`'s 38 is `x-button`'s `secondary` border) | `border-border-strong` |

`text-white` on colored backgrounds becomes the matching `-content` token, never a literal.

### Interactive

| Today | Token |
|---|---|
| `focus:ring-ocean-500` (41), `focus:border-ocean-500` (4), `focus-within:*` | `focus` |
| `text-ocean-600` (41 bare + 8 `hover:`) | `text-link` |
| `hover:text-ocean-800` (20), `text-ocean-800` (3) | `link-hover` |
| `bg-navy-900` / `hover:bg-navy-800` / `active:bg-navy-950` (`x-button` primary) | `primary` / `primary-hover` / `primary-active` |
| `border-nav-active` (the fuchsia placeholder) | `border-accent` |
| `bg-sun-200` — search `<mark>` only, written from `SearchSnippet::HIGHLIGHT_CLASS` | `highlight` (+ `highlight-content`) |
| `bg-sun-400` — **`x-table`'s `<thead>` band**, on every table in the app | `table-header` (see Q9) |

### The nav band

| Today | Token |
|---|---|
| `bg-navy-950` (nav bar) | `bg-nav` |
| `bg-ocean-900`, `bg-ocean-800`, `hover:bg-ocean-700` (project picker, dropdown triggers) | `nav-raised` + its hover |
| `text-aqua-100` (6), `hover:text-white`, `focus:text-white` | `nav-content` |
| `text-aqua-200`, `text-aqua-300`, `focus:ring-aqua-300` | `nav-content-muted`, `focus` |
| `bg-ocean-700` (`layouts/app` header band) | `nav-raised` |

### Status

`red-*` → `danger`, `green-*`/`emerald-*` → `success`, `amber-*`/`yellow-*` → `warning`,
`blue-*`/`indigo-*`/`bg-aqua-50` → `info`, each with `-content` and `-surface`. Read each of
the ~145 usages as *is this status, or is it decoration?* — `x-badge`'s `indigo` and `gray`
variants are a public API named by hue and need renaming in the same pass (see `ui.md`).

### `app.css`'s own hand-written rules — 42 references, none of them a class

The map above is Blade-classes-only, but `resources/css/app.css` references hue variables
directly **outside** `@theme`, and a Blade-only sweep leaves every one of them dangling the
moment the ramps are deleted:

| Block | References |
|---|---|
| `.tiptap` placeholder + tables | `gray-400/300/50`, `ocean-50` |
| `.wysiwyg-slash` menu | `gray-200/700/400`, `ocean-100/800` |
| `blockquote[data-callout-type]` × 5 | `blue`, `green`, `purple`, `amber`, `red` — 500 + 700 each |
| `.revision-diff*` | ~20: `green-100/400/700`, `red-100/400/700`, `blue-100/300/500/800`, `gray-50/200/300/600/700/900` |

**This is where `danger-surface` / `success-surface` / `info-surface` earn their existence** —
the tinted-background tokens have no Blade caller at all; these callout and diff rules are it.

`.diff-note`'s comment says it is "kept in step with `badge.blade.php`'s `info` variant" — that
coupling is real, and the badge rename must move both.

## Order of work

1. `Oklch` + `ColorContrast` with unit tests — nothing renders yet.
2. `ThemePreset`, `ThemeTokens`, and `config/themes.php` with **Daylight only** (literal
   values); then `users.theme_slug` + the picker once a second preset exists.
3. `@theme` role tokens + `ThemeStyleBlock` + `<x-theme-style />` in all four layouts.
4. The sweep, in component-library order (`components/` first, then layouts, then pages) —
   `x-button`, `x-badge`, `x-card`, `x-table`, form controls cover most of the surface.
5. Delete the ramps, the border shim, `--color-nav-active`. Diff computed styles against
   `master` — this is the pixel-stability gate.
6. `theme:ramp`, then Dusk + Low-glare dark. **This is where the vocabulary gets validated** —
   expect to discover a missing token here, and add it rather than fudging a value.
7. Appearance form, docs.

Steps 4 and 6 are the ones that expand; 1–3 are small.
