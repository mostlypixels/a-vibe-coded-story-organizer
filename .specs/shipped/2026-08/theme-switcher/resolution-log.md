# Theme Switcher — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Contrast ceiling is config, not a column.** It is read only when authoring a preset and by
  the tests guarding those choices — never while rendering. Floors (4.5/3.0) stay global and
  reject; the ceiling is per-preset and warns, because halation is not the only reason to hold
  an opinion about the top of the band and different conditions pull in opposite directions.
- **No `themes` table.** Nothing about a theme varies per row at runtime in this spec. Presets
  live in `config/themes.php`; the only runtime-varying value is `users.theme_slug`. Spec 3
  introduces the table when a row genuinely varies per user.
- **Per-user, not a global singleton.** There are no guests past login except the share page,
  so every themed view has a known user. `users.theme_slug` nullable → `config('themes.default')`;
  the share page and login use the default.
- **Picker lives at `/admin/appearance`.** The Configuration area already renders per-user data
  (`DataTransferController` scopes every list to `$request->user()`), so a per-user preference
  is not out of place there, and the placeholder section already exists.
- **Ramp generation is an artisan command**, not an `app/Support` class — it has no runtime
  caller. Spec 3 promotes it if its live picker needs one.
- **No cache on the rendered style block.** Default store is `database`, so caching would trade
  ~30 `sprintf` calls for a SQL round-trip per page render.
- Three presets, not four. A high-contrast preset was deferred — it is the one whose value
  depends on user feedback that does not exist yet.
- `/` keeps its route but is stripped to a themed login button; registration is already
  disabled. This is also what removes the last `dark:` classes.
- **The status trio gains a fourth token, `<status>-surface-content`.** Resolved at the
  `ship-plan` drift check, replacing the two open questions previously logged here. A trio
  leaves the tint's foreground unnamed, so `<status>-surface`'s partner defaulted to
  `<status>` — measured, that is 1.85 (warning), 3.14 (success), 4.47 (danger), 4.82 (info).
  Three of four below the text floor, not "slightly less contrast" as first logged. The fourth
  token also lets Daylight hold today's `X-800`, keeping the sweep rename-only. Lands in task
  03, because a preset must define exactly `ALL` and task 03 authors one.
- **Accessibility outranks pixel-stability — Daylight gets re-authored.** User's call, stated
  directly. Daylight fails 15 of 50 pairs as inherited; task 12 fixes all of them rather than
  exempting the default theme from its own matrix. The pixel-identical invariant is retired and
  replaced by a narrower one: the *sweep* is rename-only through task 11, and task 12 is the
  only task licensed to change a color. Task 11's diff keeps its meaning as the gate proving
  the rename was inert.
- **`border` is decorative and carries no contrast floor.** Holding a table-row hairline to 3:1
  moves it from `#e5e7eb` to `#8a8c90` — every card edge and divider in the app as a mid-grey
  line, which fails the "usable" half of the user's criterion. WCAG 1.4.11 covers what
  identifies a component or its state; a divider is not that. `border-strong` and `focus` keep
  the floor. Implemented as `ThemeTokens::DECORATIVE`, skipped by the matrix, so the exemption
  is one named list rather than scattered test conditionals.
- **Daylight declares `contrast_ceiling: 18.0`.** `primary-content` on `primary-active` is
  16.96 — white on navy-950, the pressed state of a primary button. It fails as `TooHigh`
  against the 15.0 default, which is not an accessibility problem. Per-preset ceilings already
  exist for exactly this; the 15.0 default stays for the generated presets, where halation is
  the real concern.
- **`ThemePreset::resolve(?string $slug)` is the single entry point from a stored
  `theme_slug` to a preset**, used by both `<x-theme-style />` and
  `AppearanceController`. The task file only spells out `<x-theme-style />`'s
  resolution (`auth()->user()?->theme_slug ?? config('themes.default')`), but the
  "stale slug falls back to the default rather than throwing" test requires a
  second check — the slug can be non-null and still not match any configured
  preset (removed from `config/themes.php` after a user picked it) — and
  `AppearanceController::edit()` needs the identical fallback to mark the right
  radio active. One static method on `ThemePreset` keeps that fallback in one
  place rather than duplicated inline in the component and the controller.
- **The ramp's lightness curve is linear, 0.97 → 0.15, and that floor is deliberate.** Equal
  steps are the point — perceptual evenness is the property the five hand-eyeballed sRGB ramps
  lack — so the curve has no shoulder to tune. 0.15 sits below the app's current darkest colour
  (`navy-950` is 0.24) because a dark preset needs a page background, a sunken well and a raised
  card all below the midpoint, and a floor of 0.24 leaves room for none of them. `--against`
  takes colour values, not token references: paste the value out of `config/themes.php`.
- **The modal backdrop gets its own `scrim` token** — added in task 07, `ALL` 45 → 46. Task 05
  found the hole and correctly refused to close it alone: the migration map sends `gray-500` to
  `content-muted`, but `content-muted` is body text, which every dark preset must make *light*,
  so the dark theme's modal washed the page out instead of dimming it. No token in `ALL` is
  mid-dark in Daylight and dark in the dark preset, so this was not fixable by re-authoring a
  value in task 12. Rejected `bg-black/50` because a black scrim over an already-dark UI barely
  reads — a preset needs to choose its own. `scrim` carries no text, so it takes no foreground
  partner and joins `border` in `DECORATIVE`, which the contrast matrix skips.
- **Task 05 carries three accepted visual changes**, the only ones before task 12: `x-badge`'s
  tint lightens (`X-100` → `X-50`, since one `<status>-surface` cannot hold both the badge's
  and the alert's tint), and `x-alert`'s border (`X-200`) and icon (`X-400`) both darken to
  `<status>`. Adding a fifth and sixth token per status to preserve one shade each was judged
  not worth it.

- **Task 05's accepted visual changes are six, not three.** The three the plan named (badge tint
  `X-100` → `X-50`, alert border `X-200` → `<status>`, alert icon `X-400` → `<status>`) plus three
  the migration map has no row for: `x-button`'s `danger`/`success`/`warning` **focus rings** move
  from their own hue to `focus` (`ui.md`'s "every `focus:ring-*` must land on `focus`" read
  literally — a themeable focus affordance is worth more than three matching rings); those same
  variants' **hover/active** shades become alpha on the fill (`hover:bg-danger/90`,
  `active:bg-danger/80`) because only `primary` has hover/active tokens, which inverts the active
  step from darker to lighter in Daylight; and `x-sortable-header`'s hover **darkening** becomes
  an underline, with the sort arrow at `table-header-content/70` instead of `navy-700`, because
  the header band has exactly one chosen foreground. Task 11's diff should expect six classes of
  difference, not three.

- **Dusk is a dimmed *light* theme, not a second dark one.** `spec.md` says only "dim, moderate
  contrast", which could equally have meant a warmer, punchier dark theme. Read as the middle of
  the brightness progression Daylight → Dusk → Low-glare dark: no white anywhere, the page at
  neutral-100, elevation rising from there. Two dark presets differing mainly in hue would have
  left nobody served between "white page" and "dark page", which is the gap most eye-strain
  complaints sit in.

## Deviations from the spec/plan

- **The WCAG floors are `public const` on `ColorContrast`, not `config('themes.contrast')`.**
  Task 01's own signature — `verdict(float $ratio, bool $isText, float $ceiling)` — resolves the
  floor *inside* the class, and task 01 ships no config. So `TEXT_FLOOR = 4.5` /
  `NON_TEXT_FLOOR = 3.0` live there, documented as fixed WCAG minimums. The decision they bend
  ("no contrast constant in these classes") was aimed at the ceiling, which is still always a
  parameter. **Task 02 must not re-declare 4.5/3.0 in `config/themes.php`** — reference the
  constants if the floors are needed there at all.
- `verdict()` takes `bool $isText`, per the task file, not `string $contrastClass` as
  `expanded/architecture.md` writes it. `ThemeTokens::NON_TEXT` (task 02) is a flat list, so the
  callsite already knows the answer as a boolean and an extra vocabulary buys nothing.
- `verdict()` raises a ceiling below the applicable floor up to that floor, so no ratio can be
  judged both `TooLow` and `TooHigh`. Same clamp the overview mandates for preset authoring,
  enforced one level lower as well.
- **`ThemeTokens::PAIRS` maps a background to a *list* of foregrounds**, not to the single one
  `expanded/data-model.md` sketches. A one-to-one map has no room for `content-muted`,
  `content-subtle`, `link`, `link-hover`, `border` and `focus` — all six land on `surface`, and
  only one of them could be a value — so the contrast matrix would silently skip the tokens most
  likely to be unreadable. `ThemePresetTest` asserts every token in `ALL` appears in `PAIRS` as a
  key or inside a list, so a new token cannot be added without choosing its partner.
- **Daylight stores `oklch()` verbatim wherever the current value is `oklch()`.** The plan says
  "literal hex values", but only the five hand-authored ramps (ocean/aqua/navy/sun/flame) are
  hex — Tailwind 4's own palette is OKLCH, so `gray-100` etc. are copied across as
  `oklch(96.7% 0.003 264.542)`. A hex approximation would move the pixel by a digit and make the
  computed-style diff lie. `ThemeStyleBlock` validates both notations.
- **The value whitelist moved to `Oklch::CSS_VALUE_PATTERN`; `ThemeStyleBlock` references it.**
  The plan says `fromCss()` should reuse the style block's notation, which reads as the pattern
  staying put. Inverted, because the dependency only works one way: the parser cannot be built
  from a private const on a service. Consequence to know before loosening either — `fromCss()`
  is stricter than `fromHex()`: it requires the `#` and rejects surrounding whitespace, so the
  set it parses and the set that reaches the page are the same set by construction, not by
  agreement. `fromHex()` still accepts `abc` / `#abc` for non-CSS callers.
- **`Oklch::fittedToSrgb()` is public, and `theme:ramp` emits fitted shades.** A ramp holds one
  chroma across every lightness (that is what `--max-chroma` clamps), and the extremes of a
  saturated hue then fall outside sRGB. Writing the unfitted triple into a preset would store a
  colour the browser gamut-maps by its own rules, so the config value would not be the painted
  value — and the contrast figures printed beside it, which fit via `relativeLuminance()`, would
  describe a different colour than the one stored. Fitting reduces chroma only, so hue and the
  chroma cap both still hold.
- **`app.css`'s role-token block is `@theme static`, and its values are `var()` references to
  the ramps** rather than copied literals — so Daylight's 41 values live in `config/themes.php`
  only and the two cannot drift while the sweep is in flight. `static` is required, not
  optional: nothing references these tokens until task 05 starts renaming, so plain `@theme`
  tree-shakes all 41 out of the compiled `:root` and the runtime override has nothing to
  override. **Task 11 inherits an obligation**: deleting the ramps means substituting Daylight's
  literal values into that block.

- **The migration map has no row for `text-gray-800` / `text-gray-900`** (43 usages, 28 files),
  and no token equals them: `content` is `navy-900`. Swept to `content` anyway — it is the only
  "page's own voice" token, and the alternative is a seventh vocabulary entry for one shade. In
  `x-heading` that collapses six grey shades to two content tokens (levels 1–3 `content`, 4–6
  `content-muted`); size and weight already separate the levels. Later sweep tasks should do the
  same rather than re-deciding per file. Daylight's headings therefore shift from near-black to
  navy-900 — a seventh expected difference in task 11's diff, and the only one that is app-wide.
- **`text-gray-300` as an icon/divider tint** (`x-table-empty`'s empty-state glyph,
  `x-breadcrumbs`' chevron) went to `content-subtle` (`gray-400`), not to `border-strong`, which
  is the value-exact match. Painting text with a border token is exactly the lie the vocabulary
  exists to prevent; a decorative glyph one step darker is the cheaper price.
- **`x-dropdown-link`'s active item is `accent-surface`/`accent-content`, and both states now
  take a focus ring instead of a background tint.** It used to be `bg-aqua-50` deepening to
  `focus:bg-aqua-100`; the flat vocabulary has no second shade of a tint, so a focus state
  expressed as "the same tint, slightly stronger" is unrepresentable. A ring on the `focus`
  token is expressible, themeable and a stronger affordance — but it is a
  change of kind, not a rename. `accent` (rather than `info`, which the map suggests for
  `bg-aqua-50`) keeps "this is the one you are on" a single colour idea with the active-nav
  indicator.

- **The generated presets' surfaces and content weights are half-steps, not ramp shades.** The
  ramp's 0.082 lightness stride is a palette step; elevation is a fraction of one. Four surfaces
  a full stride apart span more contrast than the gap between the 4.5 floor and a 10–12 ceiling,
  so no single body-text colour can clear the floor on the darkest surface and stay under the
  ceiling on the lightest. Everything else in both presets is a ramp shade; the anchors are in
  the config comments.

- **Only a representative walk was done, not "every page under each preset".** Nine surfaces per
  preset — dashboard, story overview, the scenes table, search with `<mark>` hits, a form-heavy
  edit page, the import page, the Appearance picker, a flash alert and the modal scrim. Chosen
  to cover every token this task moved at least once. Task 11's computed-style gate already
  crawls all 45 pages and is the thing that would catch a page-specific miss; re-running it here
  would have compared the new Daylight against `master`, which is now expected to differ.

## Issues → resolutions

- **`ColorContrast` cannot read most of Daylight.** `ratio()` accepts `Oklch|string` and sends
  every string to `Oklch::fromHex()`, which throws on `oklch(…)` — and ~30 of Daylight's tokens
  are stored in that notation (see the deviation below). `ThemeStyleBlock` validates the
  syntax but never parses it, so nothing in the codebase converts an `oklch()` string to an
  `Oklch`. Task 12's matrix could not have run. Fix: `Oklch::fromCss()` handling both
  notations, with `ColorContrast::resolve()` routed through it — added to task 03, which needs
  it to print verdicts anyway.

- **`@dataProvider` docblock annotations are silently ignored** — the test ran with zero
  arguments and died on `ArgumentCountError`. Root cause: PHPUnit 13 removed annotation-based
  metadata; only the `#[DataProvider]` attribute is read. Fix: use the attribute (as
  `tests/Feature/AutosavableFieldsAndHasRevisionsTest` already does).

- **The modal scrim has no token that works in both directions — unresolved, needs a decision
  before task 12.** `x-modal`'s backdrop was `bg-gray-500 opacity-75`, so the map's
  `gray-500 → content-muted` row applies and task 05 followed it. But `content-muted` is *body
  text*: any dark preset must make it light, and the browser pass shows the dark theme's modal
  washing the page out in pale grey instead of dimming it (screenshot: the dialog reads as a
  light haze over a dark app). No token in `ALL` is mid-dark in Daylight *and* dark in
  `low-glare-dark` — `nav` and `surface-sunken` are dark in both but move Daylight's scrim a
  long way, and `neutral`/`border-strong` are light in the dark preset too. Task 12 cannot fix
  this by re-authoring a value, because the value that breaks it is the one `content-muted` has
  to have. The three real options are a `scrim` token (a vocabulary change, which the overview
  says not to make alone), binding the scrim to `nav`, or accepting a hue-free literal
  (`bg-black/50`) on the grounds that a scrim is an effect rather than a themed surface. Left on
  `bg-content-muted` for now, so the sweep stays rename-only and the choice stays open.

- **Alpha utilities bake a literal fallback into the stylesheet.** `bg-danger/90` compiles to
  two rules: `background-color:#e40014e6` and, inside `@supports (color: color-mix(…))`, the
  `color-mix(in oklab, var(--color-danger) 90%, transparent)` that respects the runtime override.
  Every browser this app targets supports `color-mix`, so the override wins — but a browser
  without it would paint Daylight's red under any preset. Worth knowing before reaching for `/NN`
  anywhere the fallback would be visible; it is the same shape of trap as `@theme inline`, just
  narrower.

- **`x-scene-status-badge` is in no sweep task's scope.** It is a component, so tasks 08/09 (admin,
  codex, pages) do not name it, and task 06's list (form controls, pickers, `autosave-*`,
  `revision-*`, `icon-*`, nav links) does not either. Parked on `NoHueNamedColorsTest`'s
  allow-list under task 06 so it cannot be lost — whichever task reaches it first should sweep it
  and drop the line.

  Swept in task 06 itself. `draft`/`final` land exactly on `neutral`/`success` (Daylight's
  `gray-100`/`gray-700` and `green-800` are the same values as `x-badge`'s tokens); `to_proofread`
  (`yellow`) → `warning`; `to_edit` was `orange`, which has no token, and was reassigned to
  `danger` — a hue change, not a shade, since `warning` was already spoken for. `x-badge`'s own
  accepted tint-lightening (task 05, `X-100` → `X-50`) applies here too, unlogged twice.

- **Several task 06 components used a shade with no single-token equivalent** — the vocabulary is
  flat (no `-500`/`-800` suffixes), and a few pre-existing colours sat between two tokens rather
  than on one. Judgement calls, by role rather than by hue:
  - `navy-500` (icon-button's default outline, `autosave-field`'s History link) → `link`
    border/text with `info-surface` on hover — same blue family, and `info-surface` is
    near-value-identical to the old `navy-50` hover tint.
  - Icon-button's `light` variant (over the reference-image lightbox) and `x-tooltip`'s bubble
    both need to paint light-on-dark regardless of the active preset — one over a photo, the
    other a floating hint that must stay legible on any page. No token guarantees "dark surface,
    light text" in every preset except the `nav` family, so both reuse `bg-nav`/`text-nav-content`.
  - `responsive-nav-link`'s hover (`navy-800`, the mobile menu's own hover shade, no nav-specific
    hover token) → `nav-raised`, the nearest "elevated within the dark band" token, even though
    the literal values differ.
  - `x-color-picker`'s selection ring (`ring-gray-800`) → `border-strong`; the swatches themselves
    (`PlotlineColors::PRESETS` hex values) are untouched, per the task file — they are content, not
    chrome.

- **`x-text-input` / `x-select` / `x-textarea` gained `bg-surface-raised`**, which none of the
  three declared before this task. They always rendered the browser's native white background
  regardless of theme — invisible in Daylight (byte-identical to native white, so no computed-style
  diff) but a glaring unthemed box in the low-glare-dark browser pass, exactly the kind of miss the
  dark preset exists to surface. Not a rename (there was no class to rename), so it sits just
  outside the sweep's "rename-only" letter, but squarely inside its intent — `FormControlComponentTest`
  now asserts the class too.

## Issues → resolutions

- **Four component tests hard-coded the pre-sweep Tailwind class names as their assertion
  strings**, so renaming the classes broke `NavigationTest` (`text-white border-nav-active` /
  `border-nav-active text-start`, its own documented "sanctioned hook" for an active nav trigger
  with no `aria-current`), `IconButtonComponentTest` (`border-navy-500`, `text-gray-400`,
  `border-white`/`text-white`, `border-red-600`), and `FormControlComponentTest`
  (`border-gray-300 focus:border-ocean-500 focus:ring-ocean-500`). Updated all four to the
  renamed tokens rather than restructuring the components to dodge it — the marker approach itself
  is sound (documented, targeted, cheap), it just needed the same rename its subject got.
  `WordCountComponentTest` needed the same one-line fix and was not in the task file's own test
  list.

- **The modal scrim's "no token works in both directions" problem (logged above, under task 05)
  is resolved by task 07's `scrim` token.** `ThemeTokens::ALL` gains `scrim` (45 → 46), joining
  `border` in `DECORATIVE` — a background with no foreground partner, so
  `ThemePresetTest::test_every_token_appears_in_pairs` had to stop requiring `DECORATIVE` tokens
  to be covered by `PAIRS`. Daylight's value is `gray-500`, byte-identical to the old
  `bg-content-muted` the backdrop already painted, so this stays rename-only there. Low-glare-dark
  gets its own value, `oklch(0.05 0 0)` (near-black) rather than reusing that preset's
  `content-muted` — reusing it would reintroduce the exact bug the token exists to fix, since
  `content-muted` has to be *light* in a dark preset.
- **`layouts/navigation.blade.php`'s project-picker button had no single-token match for its
  hover/elevation shades.** The nav family has exactly two levels (`nav`, `nav-raised`); there is
  no third "more raised" token for `bg-ocean-900 → hover:bg-ocean-800` or `bg-ocean-800 →
  hover:bg-ocean-700` to land on. Followed `x-button`'s established pattern for a fill with no
  dedicated hover token — alpha on the same fill (`hover:bg-nav-raised/80`) — accepting the same
  documented inversion `x-button`'s own comment names ("lightens on a light theme and darkens on a
  dark one"): here it blends toward the darker `nav` band behind it instead of lightening.
  Consolidating both buttons onto `nav-raised` (the grill's own mapping) also merges the "no
  project chosen" button's base shade (`ocean-800`, lighter) into the "project chosen" button's
  (`ocean-900`, darker) — a small but real Daylight pixel change — and collapses the "dimmer until
  hover" distinction between the two states, since the flat vocabulary has no second shade to hold
  it with.
- **`border-navy-800` (the two dividers inside the mobile menu) had no border-family token at that
  value.** Reused `border-nav-raised` — the same "nearest elevated-within-dark-band" judgement call
  task 06 made for `responsive-nav-link`'s hover shade — rather than adding a nav-specific border
  token for two usages.
- **`text-gray-500 hover:text-gray-700` (and `-600 hover:-900`) — the app's recurring muted
  "Cancel" / "Back to X" / "Clear" link — has no literal-map answer that keeps the hover
  distinct.** Both shades collapse to `content-muted` under the map, which would silently drop
  the hover-darkens affordance across ~10 links in this task alone (more remain in task 09's
  scope, same idiom). Used `text-content-muted hover:text-content` instead, reusing
  `sidebar-link`'s own `tab` variant (task 06), which resolved the identical ambiguity the same
  way. Task 09 should follow suit rather than re-deciding per file.
- **The codex entry form's WAI-ARIA reference-media tabs (`codex/partials/fields.blade.php`) had
  no map row** for `border-ocean-500 text-gray-900` (active) / `border-transparent text-gray-500
  hover:text-gray-900 hover:border-gray-300` (inactive). Reused `sidebar-link`'s `tab` variant
  classes verbatim (`border-accent text-content` / `border-transparent text-content-muted
  hover:text-content hover:border-border-strong`) rather than inventing a second tab convention.
- **Two file-input color schemes recur with no dedicated hover shade**: the archive-upload
  input (`admin/data/import.blade.php`, an ocean/blue tint) and the image-upload inputs
  (`codex/partials/fields.blade.php` ×3, a plain gray tint — `chapters/edit.blade.php` and
  `projects/edit.blade.php` in task 09's scope share the exact same gray classes). Mapped the
  blue tint to `info-surface`/`info-surface-content` (matching `icon-button`'s `outline-solid`
  hover, the established "aqua tint" token) and the gray tint to `neutral`/`neutral-content`
  (badge's own pair, value-identical to `gray-100`/`gray-700`). Neither pair has a second shade
  for the hover-darkens state, so both take `x-button`'s established alpha-on-the-fill technique
  (`hover:file:bg-info-surface/80` / `hover:file:bg-neutral/80`) rather than a no-op hover.
- **`codex/partials/fields.blade.php`'s lightbox and file-preview overlays reused `bg-gray-500
  opacity-75`, the exact pre-sweep scrim `x-modal` used before task 07 introduced `scrim`.**
  Swept both to `bg-scrim opacity-75`, matching `x-modal` exactly rather than re-deriving the
  same decision — this is the same backdrop problem task 07 already solved, just a second
  caller of it.

- **`NoHueNamedColorsTest`'s pattern does not catch Tailwind's built-in status colours** —
  `red-*`/`green-*`/`blue-*`/`amber-*`/`yellow-*`/`indigo-*`/`emerald-*`/`purple-*` are outside
  `PATTERN` by design (it only scans the five hand-authored ramps, `gray`/`slate`, and the two
  `white` literals). Task 09 swept these anyway, per `architecture.md`'s Status section
  (`border-red-500`/`text-red-600` → `danger`, `bg-green-50`/`text-green-700` →
  `success-surface`/`success-surface-content`, across `scenes/index`, `scenes/create`,
  `story/index`, `projects/edit`), but nothing re-checks the rest of the app for them. A file
  outside every sweep task's file list can carry one of these indefinitely with every test
  green; tasks 10–13 should grep for them explicitly rather than trust the allow-list going
  empty.
- **Two components outside every sweep task's file list still named a hue: `x-input-error`
  (`text-red-600`) and `x-auth-session-status` (`text-green-600`).** Same shape as
  `x-scene-status-badge` in task 06's note — no sweep task's scope (chrome/forms-nav/layouts/
  admin-codex/pages) names either, and `NoHueNamedColorsTest`'s allow-list never listed them
  because its pattern doesn't scan for `red`/`green` (see above). Both render on pages task 09
  touches (every form's validation errors; `auth/verify-email`, `auth/login` via
  `session('status')`), so swept them here rather than leaving a stray hue live under the
  sweep's own dark-preset browser pass: `text-red-600` → `text-danger`, `text-green-600` →
  `text-success`.
- **The Dashboard's list/grid view toggle (`bg-ocean-50 text-ocean-600 border-ocean-500` active,
  `bg-white text-gray-500 border-gray-300 hover:bg-gray-50` inactive) has no migration-map row**
  — it is a segmented control, not a covered pattern. Mapped active to
  `bg-accent-surface text-accent-content border-accent` (the sidebar's own "this is the one
  you're on" tint, task 05/06/07's established pairing) and inactive to
  `bg-surface-raised text-content-muted border-border-strong hover:bg-surface-sunken` (literal
  value match per the map's `bg-white`/`bg-gray-300`/`bg-gray-50` rows).
- **`story/index.blade.php`'s per-act divider bar (`text-white bg-gray-600`) reused
  `bg-nav`/`text-nav-content`** — not because it's part of the nav band, but because it needs
  the same "dark surface, light text, in every preset" guarantee task 06 already named for
  `icon-button`'s `light` variant and `x-tooltip`'s bubble. No token in `ALL` other than the
  `nav` family carries that guarantee, so this is a third caller of the same judgment call.
- **`search/index.blade.php`'s match-mode radio buttons used `text-navy-900` as their accent
  colour** (not the `text-ocean-600`/`text-link` every checkbox elsewhere in the app settled
  on) — mapped to `text-primary`, the literal value match (`navy-900` is `x-button`'s primary
  fill), rather than folding it into the checkbox convention and losing the darker accent.
- **`scenes/create`/`scenes/edit`'s "new event" inline fieldset divider (`border-l-2
  border-ocean-200`) and the identical divider nowhere else in the app** — no established
  convention for this one; mapped to plain `border-border` as a decorative structural divider,
  not `border-strong` (it marks nested content, not a control or its state).

- **Task 10's own Tests bullet ("`NoHueNamedColorsTest` allow-list now contains only
  `welcome.blade.php`") is wrong, and task 02's resolution-log entry already said why.**
  `app.css`'s `@theme static` role-token block stores its values as `var()` references to the
  five ramps *on purpose* (task 02: "so Daylight's config values have exactly one twin while
  the sweep is in flight"), and the raw ramp declarations above it obviously still name every
  shade too — both are still there until task 11 deletes the ramps. `resources/css/app.css`
  therefore stays on `ALLOWED`, narrowed to a comment explaining only `@theme` is left; the 42
  references the task actually scoped (`.tiptap`, `.wysiwyg-slash`, the five callout types,
  `.revision-diff*`) are swept, and `badge.js`/`badge.test.js` came off the list as expected.
- **`NoHueNamedColorsTest::PATTERN` widened past the nine hues the task text named** (`red`,
  `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `blue`, `indigo`, `purple`,
  `fuchsia`, `pink`, `rose`, `cyan`, `teal`, `sky`, `violet`, `zinc`, `stone`, `neutral`) —
  every Tailwind v4 hue, not just the ones with an obvious status meaning. Checked first: a
  scan for the extra hues (`cyan`/`teal`/`sky`/`violet`/`fuchsia`/`pink`/`rose`/`lime`/`zinc`/
  `neutral`/`stone`) turned up matches only in `welcome.blade.php` (already allow-listed for
  task 13), so widening further cost nothing extra.
- **Several of `app.css`'s hand-written rules used a shade with no single-token equivalent**
  (the callout blockquotes' 500/700 border+label pair, the diff markers' 100/400/700 triad) —
  same shape of judgement call as every prior sweep task, decided by role rather than by
  chasing the exact shade:
  - Callout border-left → the solid `<status>` fill (matching `x-alert`'s own border); the
    `::before` label → `<status>-surface-content`, not `<status>` itself — `x-alert`'s own
    comment is why: text sitting directly on `<status>` measures as low as 1.85:1.
  - `important` (purple, no status equivalent) takes `x-badge`'s `accent` pair *wholesale*,
    background included — not just border+label like the other four — because the task names
    the pair task 05 already created for exactly this "no fifth status" case. This is a real
    hue change (purple → the app's ocean-based accent), deliberate and named in the task itself.
  - `.revision-diff ins`/`del` (both the visual and source variants) and `.diff-note` →
    `<status>-surface`/`<status>-surface-content`, matching `x-badge`'s own tinted variants
    exactly (the diff-note comment already promised to track the badge). The block-level
    `.diff-inserted`/`.diff-removed`/`.diff-formatting-changed` edge markers → the solid
    `<status>` token.
  - `.wysiwyg-slash__item.is-selected` → `accent-surface`/`accent-content`, value-identical to
    the old `ocean-100`/`ocean-800` in Daylight (same pair `x-dropdown-link`'s active state
    already carries). `.tiptap .selectedCell` reuses the same pair for the same "this is
    selected" idea, accepting a small value shift (was `ocean-50`, one shade lighter).
- **The run-imagoldfish driver had no way to type into a `contenteditable` element** — `fill`
  only works on real form fields, and Tiptap's editable region is a `contenteditable` div, so
  driving the callout toolbar button + typed text (needed to verify this task's CSS renders
  correctly under both presets) had no path. Added a `type <text>` command
  (`page.keyboard.type`) to `driver.mjs`. Also found while doing this: clicking a toolbar
  button immediately after `Control+a`/`Delete` on a freshly-emptied editor silently no-ops the
  very first Tiptap command that follows (observed with the Callout button specifically) —
  a `press Home` between the delete and the first command reliably avoids it. Worth knowing for
  any future browser-driven editor verification.

- **Deleting the ramps duplicates Daylight's 46 values into `app.css`, and only a test stops
  them drifting.** Task 02 stored the `@theme static` block as `var()` references to the ramps
  precisely so each colour had one home; with the ramps gone the block has to carry literals,
  and nothing about a divergence is visible at runtime (the `<x-theme-style />` block overrides
  every one of them on every page, so the stylesheet copy only paints if that block is missing).
  Added `ThemePresetTest::test_the_compiled_theme_block_matches_the_daylight_preset`, which
  parses the block out of `resources/css/app.css` and asserts it equals
  `config('themes.presets.daylight.tokens')` token-for-token and in order. Change a Daylight
  colour in one place now and the suite says so.

- **The `x-icon-button` `ghost` variant lost its disabled dimming in the rename, found only by
  task 11's diff.** Master's disabled state was `disabled:text-gray-200` against an enabled
  `text-gray-400`; both shades collapse to `content-subtle`, so a disabled reorder arrow
  rendered identically to a live one — the affordance was gone with the whole suite green
  (`IconButtonComponentTest` asserted `disabled:text-content-subtle`, which is present and
  means nothing). The flat vocabulary has no step below `content-subtle`, so the dimming is now
  `disabled:opacity-25`, the value `x-button` already uses — and, blended over white, near
  enough the old `gray-200` that the pixel barely moves. Fixed here rather than deferred: it is
  a rename bug, not a colour choice, and task 11 is the gate that exists to catch exactly this.

- **The computed-style gate silently compared the dark preset for two runs.** The seeded dev
  user's `theme_slug` was `low-glare-dark`, left over from an earlier task's browser pass, so
  every element differed and the report read as a catastrophe. Nothing in the output said which
  preset had painted. The harness now prints `--color-primary as painted` per origin before the
  diff, and the skill documents resetting `theme_slug` to `null` first. Any future run of this
  gate should read that line before reading anything else.

- **Task 11's expected "seven accepted differences" is twenty, and the extra thirteen are
  map-conformant.** The diff (45 pages, every element, `master` vs Daylight) found 20 distinct
  colour transitions. The task file's list and this log's earlier entries name most of them;
  these five are `expanded/architecture.md`'s own migration map doing what it says, which no
  sweep task recorded as a *visual* delta because each is a rename by the book:
  `text-gray-700`/`-600` → `content-muted` (one shade lighter, ~1800 declarations, the app's
  most common body text), `text-white` inside the nav band → `nav-content` (white → aqua-100,
  every nav label and icon), `text-aqua-200` → `nav-content-muted` (one step darker),
  `bg-ocean-700` → `nav-raised` (the page-header band on every page, two steps darker). Only
  one transition had no map row and no prior entry: `bg-gray-300` on the editor toolbar's
  `w-px` divider → `bg-border` (one shade lighter) — the map covers `border-gray-300`, not a
  divider painted as a background, and `border` is the token for a hairline whatever property
  draws it.

- **What the gate does not cover, and task 12 should not assume it did.** Default states only:
  no hover, focus or disabled styling, and no page reachable only via a redirect. So `x-alert`'s
  border/icon darkening and `x-badge`'s status tints (`X-100` → `X-50`) — three of the accepted
  differences from task 05 — were never re-confirmed here, because no crawled page renders a
  flash message or a status badge. They stand on task 05's own verification.

- **Daylight's re-authoring is ten values, not the six task 12 lists.** The task file's own
  measurement folded `border-strong` into "four are `border`", but `border-strong` is in
  `NON_TEXT`, not `DECORATIVE`, and it keeps the 3:1 floor — it read 1.34 on `surface` and 1.47
  on `surface-raised` as `gray-300`, the worst non-text failures in the preset and the visible
  boundary of every input in the app. The four beyond the task's list: `border-strong`,
  `accent` (task 02 left a fuchsia placeholder on it and said task 12 owns it — no contrast
  failure, but shipping it would have been absurd), `nav-raised` (forced, see below) and
  `warning-content` rather than `warning` (see below).

- **`focus` and `nav-raised` are one decision, not two.** `focus` is a single token that must
  clear 3:1 against the light page *and* against the dark nav band, so a window for it exists
  only if `surface` is at least 9× `nav-raised` in relative luminance. Daylight's inherited pair
  (ocean-500 on ocean-900) misses by about 2% — there is no ocean shade that satisfies both — so
  the band had to darken (`#184a58` → `#123c49`) before `focus` could land at `#1e93af`. The
  same constraint is why Dusk's nav band is ocean-950/ocean-900 rather than something lighter
  and friendlier to its dim page. Anyone re-authoring a preset should size the nav band against
  `surface` first and pick `focus` afterwards, not the other way round.

- **Warning was fixed by moving the foreground, not the fill.** White on yellow-500 is 1.92:1
  and no yellow rescues it — the fill would have to darken to brown before white worked, which
  is no longer a warning colour. `warning-content` is navy-900 instead (7.21:1), the hazard
  convention, and yellow stays yellow. Consequence: `warning-content` is dark while the other
  three status foregrounds are light, in all three presets. Do not "regularize" that.

- **Low-glare dark's five measured failures were an artefact of the 15.0 default ceiling.** Task
  03 shipped it without a `contrast_ceiling`, so nothing had ever measured it against the 10.0
  the plan intended — body text on the darkest surface read 14:1 and passed. Declaring 10.0
  turned it into a whole re-authoring rather than five fixes: the surfaces compress into roughly
  half a ramp step and the three content weights into less, because the floor and the ceiling
  together leave a window only 2.2× wide and the surface spread eats most of it. Widening either
  end breaks the other, so treat those values as a solved system, not a palette to taste.

- **`ThemeContrastTest`'s data provider reads `config/themes.php` with `require`, not `config()`.**
  PHPUnit calls a provider before `setUp()`, so no application is booted and the helper is not
  available. The test body still resolves through `ThemePreset::fromSlug()`, so the ceiling comes
  from the real code path — only the list of slugs and pairs is read off disk.

- **Tailwind Typography's `prose` scale is a second, hidden colour system, and the sweep never
  touched it.** The plugin paints `strong`, headings, links, `code`, quotes and the table/quote
  borders from its own `--tw-prose-*` variables — hard-coded greys, `--tw-prose-bold` near-black
  — so those elements ignore the `text-content-muted` every `prose` callsite sets. Bold text was
  effectively invisible under the dark preset. Found by the user on the project view, after the
  full plan was green. Fixed by re-pointing the whole scale at role tokens in `app.css`, unlayered
  and after the `@plugin` line so it wins on source order at equal specificity. Two things this
  says for later: `NoHueNamedColorsTest` cannot see a colour the app never names, and a
  `prose-invert` usage would reintroduce the bug, since the `--tw-prose-invert-*` half is still
  the plugin's greys.

- **A form control does not inherit the page's `color`.** Task 06 gave the three controls
  `bg-surface-raised` after the dark preset showed them as white boxes, but set no foreground —
  so they kept the browser's near-black default and the dark preset rendered dark text on a dark
  box. The same miss as the background one, one property over, and it survived because
  `FormControlComponentTest` asserts the class list the component *has*, which cannot notice a
  class it never had. `text-content` and `placeholder:text-content-subtle` added to all three,
  and to the test's `BASE_CLASSES`.

- **Neither of these was reachable by the contrast matrix or the computed-style gate.**
  `ThemeContrastTest` measures pairs the vocabulary declares, and neither the plugin's greys nor
  a UA default is a token. Task 11's gate compares Daylight against `master`, where both bugs
  render identically correct — they only appear under a preset the gate never runs. The dark
  preset remains the only thing that finds this class of bug, and it finds it by eye.
