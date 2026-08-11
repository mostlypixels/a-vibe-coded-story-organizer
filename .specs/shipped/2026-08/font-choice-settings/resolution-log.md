# Font Choice Settings — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

Resolved during the pre-plan grill (2026-08-11):

- **Config, not enum.** `config/fonts.php` holds `name`/`stack`/`bundled`/`note` per
  family; an enum would need three parallel `match` expressions and would diverge from
  `config/themes.php`, which already homes authored slug data. Validation is
  `Rule::in(array_keys(...))`.
- **All five candidate families ship bundled** — Inter, Atkinson, Lexend, Literata,
  Source Serif 4 — rather than the recommended "one serif". Both serifs stay; the extra
  woff2 cost was accepted.
- **Font files are fetched by `scripts/fetch-fonts.sh`** from pinned fontsource CDN URLs
  and stay checked in. No `@fontsource` npm dependency, no Vite bundling.
- **Two scale settings, not one.** `ui_scale` and `manuscript_scale`, and
  `manuscript_scale` is **relative** — a percentage on `.prose` that multiplies with the
  root scale, labelled *same / larger / largest*.
- **Inter becomes the default with no backfill.** `null` stays "follow config"; the
  author picks Atkinson once. Accepted that everyone's look changes on release.
- **Live preview via Alpine**, against the open question's recommendation, safeguarded by
  a server-rendered slug→value lookup map whose unknown-key case is a no-op.
- **The WYSIWYG editable area follows the manuscript face**; its toolbar stays chrome.
- **`UpdateThemeSettingRequest` is renamed `UpdateAppearanceRequest`** — one request, one
  form, six fields.

## Deviations from the spec/plan

- `spec.md` specified `app/Enums/FontFamily` + `Rule::enum`; the plan uses
  `config/fonts.php`. Superseded by the grill decision above.
- `spec.md` said fonts are "bundled through Vite"; the shipped reality is checked-in
  woff2 with hand-written `@font-face`, which the plan follows.
- `expanded/data-model.md` and `expanded/architecture.md` describe a single `text_scale`
  column; the plan has `ui_scale` + `manuscript_scale`.
- `expanded/ui.md` specifies apply-on-submit with no preview JS; the plan adds live
  preview (task 08).

- The manuscript **sample paragraph now styles itself from
  `var(--font-manuscript)`/`var(--manuscript-scale)`/`var(--manuscript-leading)`**
  instead of the resolved values the picker task inlined. Inline resolved values
  cannot follow the live preview, so the sample would have been the one block on the
  page that did not repaint.

## Issues → resolutions

- **Lexend has no italic design upstream** — fontsource lists only a `normal`
  style for it (Google Fonts ships no italic Lexend at all). Task 02 said
  "roman + italic" for all four new families; Lexend ships roman-only, same
  as `architecture.md`'s font-files section already anticipated. Selecting
  Lexend for italic text gets a browser-synthesised oblique, the same known
  cost Atkinson already carries for having no italic at all.
- **`config/fonts.php`'s `atkinson.stack` named the wrong CSS family.** It
  read `'Atkinson Hyperlegible'`, but the bundled `@font-face` (and
  `--font-sans`) use `'Atkinson Hyperlegible Next'` — task 01's config never
  had CSS to check against, so the mismatch was silent until task 02's
  config-vs-`@font-face` drift test caught it. Fixed by pointing `stack` at
  the `…Next` family; `name` (the picker's display label) is unchanged.
- The new drift test compares `@font-face` names against `stack`'s primary
  family, not `name` — `name` is a display label and is allowed to differ
  (see the Atkinson case above), so comparing it would false-positive.
- **The WYSIWYG editable area's class list lives in `resources/js/wysiwyg.js`
  (Tiptap's `editorProps.attributes.class`), not in
  `wysiwyg.blade.php`.** The Blade file only renders the pre-mount
  `<textarea>` and the toolbar chrome; `font-manuscript` was added to the
  JS-side class string instead. `wysiwyg.blade.php` itself needed no change.
- **`--manuscript-scale`/`--manuscript-leading` had no `@theme static`
  fallback in `app.css`**, unlike `--font-manuscript` right next to them.
  `css-build.test.js` only caught it once `public/build` held a real build —
  it reported both as dangling `var()` references. Fixed by adding the same
  fallback pattern as `--font-manuscript`, valued at
  `config('fonts.default_manuscript_scale')` / `default_leading`.
