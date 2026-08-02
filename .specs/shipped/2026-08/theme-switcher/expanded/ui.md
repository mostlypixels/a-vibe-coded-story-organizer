# Theme Switcher — UI

Almost all the UI work here is a rename, not new screens. The one new surface is a preset
picker on a page that already exists.

## New

### `resources/views/components/theme-style.blade.php`

```blade
{{-- The active preset's :root overrides, so the whole app repaints without a rebuild.
     Single source of the block, for the same reason as x-robots-meta.
     Never wrap this in @layer — an unlayered :root rule is what outranks Tailwind's
     `@layer theme`; layering it would silently lose. --}}
<style>{!! app(\App\Services\ThemeStyleBlock::class)->render(
    \App\Support\ThemePreset::fromSlug(auth()->user()?->theme_slug ?? config('themes.default'))
) !!}</style>
```

`{!! !!}` is deliberate and is exactly why `ThemeStyleBlock` whitelist-validates every value
itself (`architecture.md`). Add a comment saying so — this is the line a junior developer will
copy the pattern from.

### Preset picker — `resources/views/admin/appearance/edit.blade.php`

Replaces the placeholder paragraph ("Graphical and accessibility options will live here").

Two comments currently say this page is finished, and a junior will read them as a hard no:
`admin/appearance/edit.blade.php` says *"Final v1 content… no later task enriches this page"*,
and `AppearanceController`'s docblock names `display-configurator` (spec 3) as the enricher.
Both change in this spec — call it out in the plan task rather than leaving it to be noticed.

- `<x-card>` with an `<x-heading level="3">`, per the surrounding Configuration pages.
- A radio **group**, not a `<select>`: three options, each showing name, one-line description,
  and a swatch strip. `<fieldset>` + `<legend class="sr-only">`, native `<input type="radio">`
  — arrow-key navigation comes free and nothing needs Alpine.
- Swatches: 5–6 `<span>`s per preset (`surface`, `surface-raised`, `content`, `primary`,
  `accent`, `focus`) with `style="background-color: …"` read from that `ThemePreset`'s tokens.
  Inline style is unavoidable — the values are data, not classes — so it must pass the same
  validation the style block uses.
- Submit via `<x-button>`; success via the existing `status` session flash pattern
  (`'theme-updated'`), matching `admin/settings/edit`.
- A swatch strip is **not** an accessible label. Each radio's visible text label carries the
  name; the swatches are `aria-hidden`.

No live preview and no JS in this spec — apply on submit, repaint on the next render. Live
preview is spec 3's problem, where it is worth the machinery.

## Changed — the sweep

Order the work library-first; the components below carry most of the ~900 usages, so doing
them first shrinks every page that follows.

| File | What moves |
|---|---|
| `components/button.blade.php` | all seven variants (`primary`, `secondary`, `danger`, `success`, `warning`, `ghost`, `link`); `focus:ring-ocean-500` × 4 → `focus` |
| `components/badge.blade.php` | already has `info`/`success`/`warning`/`danger`/`primary`. The two hue-named ones are **`indigo`** (which is `bg-ocean-100 text-ocean-800` — not indigo) and **`gray`**, which is also the `@props` default. Rename both to roles; `indigo`'s only caller is `revision-origin-badge.blade.php` (`RevisionOrigin::Import`) |
| `components/nav-link.blade.php`, `responsive-nav-link.blade.php` | `nav-content` / `accent` / `focus` |
| `components/sidebar-link.blade.php`, `dropdown-link.blade.php`, `icon-button.blade.php` | link + focus tokens |
| `components/card.blade.php`, `alert.blade.php`, `table`/`sortable-header` | surfaces, borders, status |
| `components/chip-picker.blade.php`, `revision-picker.blade.php`, form controls | focus + border-strong |
| `layouts/navigation.blade.php` (18 hue usages) | the whole `nav*` family |
| `layouts/app.blade.php` | `bg-ocean-700` header band, `bg-gray-100` shell |
| `layouts/guest.blade.php`, `layouts/public.blade.php` | shell + card surfaces |
| `admin/data/export-ebook.blade.php` (30 — the single heaviest file) | mostly form controls; check for classes that should have been components |
| `codex/partials/fields.blade.php` (14) | form controls |
| `components/search/result-row.blade.php` | `<mark class="bg-sun-200">` → `bg-highlight` — note the mark is injected as trusted HTML by the search snippet builder, so the class also appears in **PHP**, not only Blade |
| `components/table.blade.php` | `<thead class="bg-sun-400">` → `table-header`. Not `highlight` — this is every table in the app |
| `resources/css/app.css` | 42 hand-written hue references outside `@theme` (`.tiptap`, `.wysiwyg-slash`, callout blockquotes, `.revision-diff*`) — see `architecture.md` |
| `resources/js/autosave/badge.js` | hard-codes `border-gray-300 bg-white text-gray-600` in two places. **`badge.test.js` asserts the exact strings** — move the vitest fixture in the same commit, and remember `npm run test` is a separate command from `composer test` |

> [!WARNING]
> Grep for token names in `app/` too, not just `resources/`. `bg-sun-200` is written by
> `App\Support\SearchSnippet`; a Blade-only sweep leaves a dangling class that silently stops
> matching once the ramp is deleted.

## Accessibility

- The picker is keyboard-operable with no JS. Do not replace the radios with styled `<div>`s.
- Every `focus:ring-*` in the sweep must land on `focus`, not `primary`. They are separate
  tokens precisely so a theme can move the focus ring independently, and losing a focus
  affordance in a rename is the most likely regression in this spec — spec 1 already had to
  add a ring back on `nav-link` for this reason.
- `navigation/dropdown-trigger` still has no focus ring (spec 1's `standing-issues.md`). This
  sweep touches the file; closing that gap here is cheap and in-theme. Note it either way.
- Preset names must be translated (`__()`), like `AdminNavigation`'s labels.

## Browser pass

Repeat spec 1's method: walk every page under **each** of the three presets. Daylight is a
computed-style diff against `master` and should be empty. Dusk and Low-glare are read by eye —
what you are looking for is two tokens that collapsed into one value (a card that vanishes into
the page), and text that has no `-content` pair and stayed dark on a dark surface.
