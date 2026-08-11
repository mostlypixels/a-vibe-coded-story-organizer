# Font choice — deep dive

The short version lives in [`architecture.md` → Font choice](architecture.md#font-choice).
This page is the reference: the invariants, the pitfalls, and the reasoning the code
can't state on its own.

## Why config, not an enum

`spec.md` originally specified `App\Enums\FontFamily` + `Rule::enum`. `config/fonts.php`
replaced it: a family needs `stack` (the CSS value), `bundled` and `note` alongside its
slug, which an enum can only carry via three parallel `match` expressions. Same reasoning
as `config/themes.php` — self-hosted, so adding a font is a file edit, not a migration.

Five slug lists live there: `families` (shared by both the UI and manuscript pickers —
one list, two choices into it), `ui_scales`, `manuscript_scales`, `leading`, and the five
`default_*` keys every one of those falls back to. `FontConfigTest` asserts every default
is itself a key of its own list.

## The only thing validated is the slug

`stack` is a full CSS `font-family` value, already quoted, written once in
`config/fonts.php` and never touched again. `UpdateAppearanceRequest` validates
`Rule::in(array_keys(config('fonts.families')))` against the *slug* — `inter`,
`atkinson`, `literata`… — never against the stack string, because the stack string
never comes from the request at all.

`App\Support\FontChoice::resolve()` is the single place a stored slug becomes a
`stack`/scale/leading value:

- `null` → the matching `config('fonts.default_*')` value.
- A slug that indexes the config → that entry's value.
- A slug that indexes nothing (a family removed from config since it was picked) →
  falls back to default, same as `null`. It never throws.

`App\Services\FontStyleBlock::render()` takes a resolved `FontChoice` and prints it into
one unlayered `:root{...}` rule, appended in the same `<style>` tag `ThemeStyleBlock`
already writes (`x-theme-style`, every layout's `<head>`).

> [!IMPORTANT]
> **Why the `{!! !!}` here is safe for a different reason than the theme block's.**
> `ThemeStyleBlock` interpolates user-adjacent color strings, so it whitelist-validates
> every value against `Oklch::CSS_VALUE_PATTERN` before printing. `FontStyleBlock` skips
> that step on purpose — there is nothing to validate. Every value it ever prints was
> authored in `config/fonts.php` and reached by an authored array key; the request body
> is never in the path. Do not add a validation step here "to match" — it would imply a
> risk that does not exist and hide the real guarantee (resolve-through-config) behind
> a pattern check that isn't doing the work.

## The JS preview needs its own copy of the rule

`resources/js/font-preview.js` repaints `document.documentElement.style` the instant a
radio changes, so the picker previews before Save. It cannot reuse the PHP guarantee —
by the time JS runs, the request has already round-tripped — so it re-implements the same
shape: `resolvePreview(maps, field, slug)` looks `slug` up in a **server-rendered map**
(`maps[field][slug]`, built from the same `config('fonts.*')` arrays the request
validates against) and returns `null` on any miss. `apply()` never reads `input.value`
into `setProperty()` directly; it always goes through `resolvePreview()` first.

`PREVIEW_PROPERTIES` maps each form field name to the CSS property `FontStyleBlock`
writes for it (`ui_font` → `--font-sans`, `manuscript_scale` → `--manuscript-scale`, …).
Two copies of that mapping — PHP's property list and JS's — would drift silently; keep
them in the same shape when either changes.

## `null` means "follow config", always

No migration, no controller, and no form default ever writes a slug into
`users.ui_font` / `manuscript_font` / `ui_scale` / `manuscript_scale` /
`manuscript_leading`. All five stay `nullable` with no `default()`. A user who has never
opened the picker reads `config('fonts.default_*')` forever, including through a config
change — that's the whole point of leaving the column empty instead of seeding "today's
default" into it.

## Why the manuscript scale is relative, not absolute

`ui_scale` sets `:root{font-size}` — a percentage that resizes the whole chrome, since
everything sized in `rem` follows the root. `manuscript_scale` is a **separate**
percentage applied to `.prose`, so the two **compose**: a reader on `ui_scale: larger`
and `manuscript_scale: larger` gets a manuscript larger than either setting alone would
produce. That composition is why the manuscript steps are labelled *same / larger /
largest* rather than a point size — "normal" would be ambiguous once `ui_scale` has
already moved the root, and a fixed size would silently un-compose the two settings.

`expanded/architecture.md` and `expanded/data-model.md` describe a single `text_scale`
column; the shipped feature has two, resolved via the grill in `resolution-log.md`.

## Exports and the public share page do not follow the choice

`resources/views/exports/epub/styles.css` and `exports/book/layout.blade.php` are
untouched by this feature and stay that way. An EPUB or static export is a fixed
artifact read on the recipient's own device with the recipient's own reader/font
settings — baking in the writer's own accessibility pick would fight the export
format's own font handling, not help it. `<x-theme-style>` (and therefore this feature)
never renders inside either path.

Guests and the public share route render `config('fonts.*')` defaults, the same as a
theme: both resolve from `auth()->user()`, which is `null` there, and `FontChoice::resolve()`
already treats `null` as "use the default" — no separate guest branch exists or should be
added.

## Adding a family

1. Add an entry to `config/fonts.php` → `families`: slug, `name`, `stack`, `bundled`,
   `note`.
2. If `bundled: true`, add its download entries to `scripts/fetch-fonts.sh` and run it to
   populate `public/fonts/`.
3. Add the matching `@font-face` block(s) in `resources/css/app.css`, using the exact
   family name `stack` references (see the Atkinson pitfall below — the display `name`
   and the CSS family name are allowed to differ).
4. Run the suite. `FontConfigTest` catches a config entry with no matching `@font-face`,
   or an `@font-face` with no config entry, from either direction — it tells you which
   half you forgot.

> [!WARNING]
> **A family's `stack` must name the same CSS `font-family` its `@font-face` block
> declares — not necessarily its display `name`.** Atkinson's bundled files declare
> `'Atkinson Hyperlegible Next'` in `@font-face`, but the picker's display label is
> "Atkinson Hyperlegible" (no "Next"). `stack` must say `'Atkinson Hyperlegible Next'`;
> only `name` is free to read however the picker should show it. The drift test compares
> `@font-face` names against `stack`'s primary family, never against `name`, for exactly
> this reason — comparing against `name` would false-positive on this entry.

> [!NOTE]
> Lexend has no italic design upstream (Google Fonts ships no italic Lexend at all), so
> it's fetched and declared roman-only, same as Atkinson already had no italic. Picking
> either for italic text gets a browser-synthesised oblique — a known, accepted cost, not
> a bug.

## Where things live

| Concern | Location |
| --- | --- |
| Family/scale/leading vocabulary, defaults | `config/fonts.php` |
| Slug → CSS value resolution | `App\Support\FontChoice::resolve()` |
| `:root` rule rendering | `App\Services\FontStyleBlock` |
| Stored preference columns | `users.ui_font`, `manuscript_font`, `ui_scale`, `manuscript_scale`, `manuscript_leading` |
| Validation | `App\Http\Requests\UpdateAppearanceRequest` |
| Picker + controller | `App\Http\Controllers\AppearanceController`, `resources/views/admin/appearance/edit.blade.php` |
| Live preview | `resources/js/font-preview.js` |
| Bundled font files | `public/fonts/`, fetched by `scripts/fetch-fonts.sh` |
| `@font-face` rules, `--font-manuscript` fallback | `resources/css/app.css` |
| Manuscript prose surfaces | `.prose` (`resources/css/app.css`), `x-rich-text`, the WYSIWYG editable area (`resources/js/wysiwyg.js`) |
