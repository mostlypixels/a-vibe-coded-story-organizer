# Architecture — Tailwind 4

No PHP changes. Everything here is the build pipeline and `resources/css/app.css`.

## Build pipeline

**Before**

```
resources/css/app.css
  → PostCSS (postcss.config.js: tailwindcss, autoprefixer)
    → Vite (laravel-vite-plugin only)
```

**After**

```
resources/css/app.css
  → Vite (laravel-vite-plugin + @tailwindcss/vite)
```

### `vite.config.js`

Add the import and the plugin. **Order matters** — `tailwindcss()` after `laravel()`.

```js
import tailwindcss from '@tailwindcss/vite';

plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
    tailwindcss(),
],
```

Leave the `server` block alone. The polling watcher (`VITE_USE_POLLING`), `origin`, and
`cors` settings exist for the Docker bind-mount case and are unrelated — but see
`open-questions.md`, since the 60s poll interval interacts with v4's own watcher.

### `package.json`

| Action | Package | Why |
|---|---|---|
| remove | `autoprefixer` | v4 prefixes internally |
| remove | `postcss` | survives as a transitive dep of Vite; not ours to declare |
| remove | `tailwindcss@^3.1.0` | replaced |
| add | `tailwindcss@^4` | the engine, now a peer of the Vite plugin |
| keep | `@tailwindcss/vite@^4.0.0` | already present, finally used |
| keep | `@tailwindcss/forms`, `@tailwindcss/typography` | 0.5.x are v4-compatible; loaded from CSS |

Delete `postcss.config.js` entirely — it declares only `tailwindcss` and `autoprefixer`, so
nothing is left to keep it alive. Vite still reads a `postcss.config.js` if one reappears
later; the two coexist fine.

Run `npm install` and confirm the duplicate nested Tailwind under `node_modules` is gone.

### `tailwind.config.js` → `@theme`

Delete the file. Its three concerns move:

| v3 config | v4 |
|---|---|
| `content: [...]` | `@source` directives (below) |
| `theme.extend.fontFamily.sans` | `--font-sans` in `@theme` |
| `theme.extend.colors` | `--color-<name>-<shade>` in `@theme` |
| `plugins: [forms, typography]` | `@plugin "@tailwindcss/forms"` / `@plugin "@tailwindcss/typography"` |

> [!WARNING]
> Use `@theme`, never `@theme inline`. `inline` substitutes the *value* into each utility
> rule instead of referencing the custom property — the utilities look identical and every
> runtime override in spec 2 silently stops working. This is the single most expensive
> mistake available in this migration, because it surfaces a spec later.

**`--font-sans` needs its fallback stack spelled out.** The config imported
`defaultTheme.fontFamily.sans` and spread it; there is no JS import in CSS, so write the
stack literally:

```css
@theme {
  --font-sans: 'Atkinson Hyperlegible Next', ui-sans-serif, system-ui, sans-serif,
               'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}
```

Copy the exact list from `node_modules/tailwindcss/…/defaultTheme` before deleting the v3
install, rather than typing it from memory. The self-hosted `@font-face` blocks at the top of
`app.css` (Figtree and Atkinson Hyperlegible Next, `public/fonts`, air-gapped by design) are
plain CSS and need no change.

The five palettes (`ocean`, `aqua`, `navy`, `sun`, `flame` — 55 values) transcribe
one-for-one, same hex, into `--color-<name>-<shade>`.

### `@source` — the part auto-detection does not cover

v4 drops the `content` array and scans the project automatically, **respecting
`.gitignore`**. Two of the three v3 globs are therefore *not* replaced by auto-detection:

| v3 glob | v4 |
|---|---|
| `./resources/views/**/*.blade.php` | auto-detected — drop it |
| `./resources/js/**/*.js` | auto-detected — drop it |
| `./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php` | **`@source` required** — `vendor/` is gitignored |
| `./storage/framework/views/*.php` | drop it — compiled output of `resources/views`, already covered |

```css
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views";
```

> [!WARNING]
> Pagination is live: `resources/views/revisions/index.blade.php` calls `->links()`. Without
> that `@source`, the revision browser's pager renders with its classes present in the HTML
> and no matching rules in the stylesheet — no build error, no failing test.

> [!NOTE]
> The source spec flagged `resources/js/wysiwyg.js:544` (`prose prose-sm max-w-none
> focus:outline-none px-3 py-2`, set from JS) as the `@source` risk. It is not — `resources/js`
> is not gitignored, so v4 finds it. The real gap is `vendor/`. That line still matters, but as
> an `outline-none` → `outline-hidden` rewrite (see `ui.md`).

## `resources/css/app.css`

### Header

```css
@import "tailwindcss";

@plugin "@tailwindcss/forms";
@plugin "@tailwindcss/typography";

@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views";

@theme { /* --font-sans, --color-* */ }
```

replaces the three `@tailwind` lines. The file uses **no `@layer` and no `@apply`** — it is
plain CSS from line 5 onward, which removes the largest category of v4 migration pain
(`@apply` in a separate stylesheet needing `@reference`). The only mechanical work is the
`theme()` rewrite below.

### The `theme()` rewrite — 86 occurrences on 81 lines

> [!WARNING]
> **This is not a search-and-replace on `colors`.** Only 42 of the 86 calls are colors. The
> other 44 span four namespaces that v4 *renamed or restructured*, and a wrong guess produces
> a `var()` referring to nothing — which CSS drops silently, taking the whole declaration
> with it. Nothing in the build or the test suite catches that.

| v3 call | count | v4 | Note |
|---|---:|---|---|
| `theme('colors.X.Y')` | 41 | `var(--color-X-Y)` | direct |
| `theme('colors.white')` | 1 | `var(--color-white)` | direct |
| `theme('spacing.N')` | 24 | `calc(var(--spacing) * N)` | **not** `var(--spacing-N)` |
| `theme('spacing[0.5]')` | 3 | `calc(var(--spacing) * 0.5)` | same rule |
| `theme('fontSize.S')` | 8 | `var(--text-S)` | namespace renamed `fontSize` → `text` |
| `theme('borderRadius.md')` | 2 | `var(--radius-md)` | namespace renamed `borderRadius` → `radius` |
| `theme('borderRadius.full')` | 1 | `var(--radius-full)` | same |
| `theme('borderRadius.DEFAULT')` | 4 | **`var(--radius-sm)`** | see below |
| `theme('lineHeight.relaxed')` | 1 | `var(--leading-relaxed)` | namespace renamed |
| `theme('boxShadow.lg')` | 1 | `var(--shadow-lg)` | namespace renamed; value unchanged at `lg` |

**Spacing is no longer a discrete scale.** v4 defines a single `--spacing: 0.25rem`
multiplier and derives every step from it, so `--spacing-1` does not exist. 27 of the 86
calls are affected — the largest single group after colors.

**`borderRadius.DEFAULT` is the trap.** v4 shifted the radius scale down a name: v3 `rounded`
(0.25rem) is v4 `rounded-sm`. There is no `--radius-DEFAULT`. The four call sites
(`app.css:266, 441, 619` and one more) each control a visible chip or badge corner, and a
dangling `var()` leaves them square with no error anywhere.

Colors in the rewrite are mostly *not* the project palette: 35 of the 42 are stock
`gray`/`red`/`green`/`blue`/`purple`/`amber`, concentrated in the revision-diff and callout
rules. Only 4 touch `ocean` (`:275`, `:276`, `:313`, `:564`). Spec 2 revisits all of them;
this spec only changes their syntax.

### Verification

After the rewrite, every `var(--…)` in `app.css` must resolve. Build, then check the emitted
stylesheet declares each referenced custom property:

```bash
grep -ohE 'var\(--[a-z0-9-]+' resources/css/app.css | sort -u
```

Cross-check that list against the `:root` block in `public/build/assets/*.css`. Anything
referenced but not declared is a silently-dropped declaration. This belongs in the plan as
its own task, not as a step inside the rewrite task.

## `npx @tailwindcss/upgrade`

Run it first — it does the directive swap, the config extraction and most of the `theme()`
rewrites. It is a starting point, not the deliverable: verify every row of the table above by
hand, and expect it to leave the `@source` decision and the `--font-sans` fallback stack to
you.

## Documentation to update

- `documentation/architecture.md` — the build-pipeline description, plus a line on why theme
  tokens are now runtime custom properties (the hook spec 2 hangs off).
- `documentation/ui-components.md` — it states that colours are written as full Tailwind
  class strings because of static extraction. Still true, and now worth naming auto-detection
  as the reason, since the `content` array it implicitly referenced is gone.
- `documentation/dependency-overrides.md` — check whether the removed `postcss`/`autoprefixer`
  entries appear there.
