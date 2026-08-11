# Font choice — deep dive

The short version lives in [`architecture.md` → Font choice](architecture.md#font-choice).
This page is the reference: the invariants, the pitfalls, and the reasoning the code
can't state on its own.

## Why config, not an enum

`spec.md` originally specified `App\Enums\FontFamily` + `Rule::enum`. `config/fonts.php`
replaced it: a family needs `stack` (the CSS value), `bundled`, `accessible` and `note`
alongside its slug, which an enum can only carry via parallel `match` expressions. Same
reasoning as `config/themes.php` — self-hosted, so adding a font is a file edit, not a
migration.

The lists there: `families` (shared by both pickers — one list, two choices into it),
`ui_scales`, `manuscript_scales`, `leading`, `leading_bases`, and a `default_*` key per
choosable list. `FontConfigTest` asserts every default is itself a key of its own list.

`accessible` marks a family drawn for impaired reading (Atkinson, Lexend). The picker
gives those an eye icon and uses `note` as its label, which is why an accessible family
may not ship a blank note.

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

## Line spacing is a multiplier, and CSS cannot apply it

`leading` holds **multipliers of each surface's default line height**, not line heights:
`1` leaves the text alone, `2` doubles it. A CSS rule cannot read the line height it
overrides, so the multiplication happens in PHP — `leading_bases` says what `1` means per
surface and `FontChoice::manuscriptLineHeight()` / `uiLineHeight()` return the product.

`leading_bases` mirrors Tailwind's own numbers: `manuscript` is Typography's `.prose`
line height, `ui` is `text-base`'s. They are not taste settings — change them only when
the framework's defaults change.

The floor is `1`. Below the default, one line's descenders collide with the next line's
ascenders, which is a defect rather than a tighter setting.

> [!NOTE]
> `1` is exact for the manuscript and approximate for the interface. One value replaces
> every `text-*` line height at once, so `text-sm` moves from ~1.43 to 1.5 — imperceptible,
> but not literally "unchanged".

## Why the interface leading is `--tw-leading`

Tailwind compiles every `text-*` utility as
`line-height: var(--tw-leading, var(--text-<size>--line-height))`. A plain `line-height`
on `:root` therefore reaches almost nothing — each utility overrides it. `FontStyleBlock`
writes `--tw-leading`, filling that same slot, so the interface leading applies
everywhere while a local `leading-*` utility still wins by setting the variable on the
element.

> [!WARNING]
> `--tw-leading` is Tailwind's internal variable, not a public API. If a major Tailwind
> upgrade renames it, the interface line spacing silently stops working — nothing errors.
> `FontStyleBlockTest` asserts the variable is emitted, which turns that into a failing
> test rather than a silent regression.

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

The line-spacing entries in that map hold **already-multiplied** values, one list per
surface (`FontChoice::lineHeightsFor()`), because the browser must not do that arithmetic
on a value it read out of the form.

> [!WARNING]
> The form carries `autocomplete="off"`. Without it, a browser restores the radios on
> reload *without firing a `change` event*, so the preview never runs while the style
> block still paints the saved values — the controls and the page then disagree on every
> field. `AppearanceSettingsTest` asserts the attribute is present.

## `null` means "follow config", always

No migration, no controller, and no form default ever writes a slug into
`users.ui_font` / `manuscript_font` / `ui_scale` / `manuscript_scale` /
`manuscript_leading` / `ui_leading`. All six stay `nullable` with no `default()`. A user who has never
opened the picker reads `config('fonts.default_*')` forever, including through a config
change — that's the whole point of leaving the column empty instead of seeding "today's
default" into it.

## Why the manuscript scale is relative, not absolute

`ui_scale` sets `:root{font-size}` — a percentage that resizes the whole chrome, since
everything sized in `rem` follows the root. `manuscript_scale` is a **separate**
percentage applied to `.prose`, so the two **compose**: a reader on `ui_scale: larger`
and `manuscript_scale: larger` gets a manuscript larger than either setting alone would
produce. That composition is why the picker prints the manuscript steps as multipliers
(`1×` … `1.6×`) and the interface steps in px (`12px` … `20px`): a px label on the
manuscript would go stale the moment the interface size moved.

Line spacing is the opposite: the two surfaces are **independent**, because a unitless
line height is already proportional to whatever font size applies. Binding them would
force one surface to inherit a ratio chosen for the other's reading task — chrome is
scanned, a manuscript is read for hours.

`expanded/architecture.md` and `expanded/data-model.md` describe a single `text_scale`
column; the shipped feature has two, resolved via the grill in `resolution-log.md`.

## The picker's controls are radios wearing costumes

Both controls in `admin/appearance/edit.blade.php` are native radio groups, so arrow
keys work, the form submits with JS off, and what posts is always a config slug — never a
numeric index that would make the order of `config/fonts.php` part of the wire format.

- **`x-font-card`** — the family name rendered in its own face on a sunken panel, with
  the eye icon for `accessible` families. The radio is `sr-only`; selection paints a flush
  ring and focus an offset outline, because a theme may give `link` and `focus` the same
  colour and the two states must then differ in shape.
- **`x-setting-track`** — the five steps of a size or spacing list drawn as a tick track,
  labels derived from the authored value (no second list of numbers to drift).
  `resources/js/setting-track.js` adds pointer dragging on top: it only moves the checked
  radio and dispatches its `change`, so the preview and the submit path are unchanged and
  dragging can fail without taking the control with it. It snaps between steps — five
  positions, no in-between.

> [!WARNING]
> An Alpine component's properties must be declared on the object the `Alpine.data()`
> factory returns. `setting-track.js` originally assigned `this.radios` in `init()` only;
> all four tracks then shared one list and every track drove the last one — with a green
> test suite throughout, because the pure functions were all correct.

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
   `accessible`, `note`.
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
> a bug. Every other bundled family ships roman and italic, latin and latin-ext.

## Where things live

| Concern | Location |
| --- | --- |
| Family/scale/leading vocabulary, defaults | `config/fonts.php` |
| Slug → CSS value resolution | `App\Support\FontChoice::resolve()` |
| `:root` rule rendering | `App\Services\FontStyleBlock` |
| Stored preference columns | `users.ui_font`, `manuscript_font`, `ui_scale`, `manuscript_scale`, `manuscript_leading`, `ui_leading` |
| Validation | `App\Http\Requests\UpdateAppearanceRequest` |
| Picker + controller | `App\Http\Controllers\AppearanceController`, `resources/views/admin/appearance/edit.blade.php` |
| Live preview | `resources/js/font-preview.js` |
| Track dragging | `resources/js/setting-track.js` |
| Picker controls | `x-font-card`, `x-setting-track` |
| Bundled font files | `public/fonts/`, fetched by `scripts/fetch-fonts.sh` |
| `@font-face` rules, `--font-manuscript` fallback | `resources/css/app.css` |
| Manuscript prose surfaces | `.prose` (`resources/css/app.css`), `x-rich-text`, the WYSIWYG editable area (`resources/js/wysiwyg.js`) |
