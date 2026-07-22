# Expand Tip Tap — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

- **Callout scope: both formats.** Callouts (and the widened allow-list generally)
  apply to the 8 HTML `RichTextFields` fields *and* `Scene.contents`, not just
  Markdown mode — one shared allow-list, no per-field fork.
- **Attribute mechanism: new `ALLOWED_ATTRIBUTES` map.** `RichTextFields` gains a
  separate `class-string tag => list<attribute>` constant rather than folding
  attributes into `ALLOWED_TAGS`'s flat list.
- **Image resize: HTML-mode fields only**, ships in v1. Off entirely for
  Markdown-mode fields (lossy there, the reason it's on the fallback-warning list).
- **Table merge/split: HTML-mode fields only**, ships in v1. Same reasoning as resize.
- **Paste-time/import-time HTML-wrapper transformation: out of scope for this plan
  entirely.** The warn-from-a-list fallback covers the residual case adequately for
  v1; sizing a paste/import transform is left for a future pass.
- **Vitest file layout: co-located** (`resources/js/<name>.test.js`), not a
  `__tests__/` directory — matches Vite/vitest's own ecosystem convention.
- **Fallback-warning structural-check list is a standalone task/module** (task 07),
  decoupled from the tables/images UI work — gives `autosave-with-revisions` one clear
  dependency.
- **Backend PHP work split into two tasks**: allow-list widening (task 01) and
  Markdown-converter consistency (task 02) are independently implementable/testable.
- **Frontend split into wiring-then-UI**: extension installation/configuration +
  vitest (task 03) precedes toolbar/slash-menu UI (task 04) — data before the UI that
  reads it.
- **Underline/Strike (task 05) and the Callout node (task 06) are separate tasks** —
  very different sizes of work (suppression removal vs. new custom node).
- **Resize/merge UI folded into the tables/images UI task (04)**, not a separate
  follow-up task.

All decisions above were resolved in the `plan-tasks` grilling session, 2026-07-22.

## Deviations from the spec/plan

- **Task list markup needed more new tags than task 01 anticipated.** The task file
  guessed "likely `ul`/`li` are already allowed, plus a checkbox-state attribute" —
  reading the installed `@tiptap/extension-list` source (`task-item.ts`) showed
  `TaskItem.renderHTML()` actually emits `<li data-type="taskItem"
  data-checked="…"><label><input type="checkbox" checked></label><span></span><div>…
  </div></li>` and `TaskList` emits `<ul data-type="taskList">`. So `label`, `input`,
  `span`, `div` all joined `RichTextFields::ALLOWED_TAGS` (div/span are free — they're
  in HTMLPurifier's core Text module — but label/input required the fix below).

## Issues → resolutions

- **HTMLPurifier rejects `<label>`/`<input>` outright even when listed in
  `HTML.Allowed`.** Root cause: HTMLPurifier's `Forms` HTML module (which defines
  `label`/`input`/`textarea`/`button`/...) ships with `$safe = false` and refuses to
  emit any of its elements unless `HTML.Forms` is explicitly set `true` on the config
  — this is independent of the `HTML.Allowed` tag list. Fix: `HtmlSanitizer` now sets
  `$config->set('HTML.Forms', true)`. This does **not** widen the surface to
  `<form>`/`<textarea>`/`<button>`/etc. — `HTML.Allowed` still gates the final tag
  set to what's in `RichTextFields::ALLOWED_TAGS`, so only `label`/`input` (the two
  Forms-module tags actually listed there) can ever appear.
- **HTMLPurifier also rejects any `data-*` attribute by default**, even when named in
  the `HTML.Allowed` directive (e.g. `ul[data-type]`) — its built-in HTML modules
  model HTML4/XHTML1, which predates `data-*`. Root cause: `HTML.Allowed` can only
  grant attributes the active `HTMLDefinition` already recognizes; `data-*` isn't
  recognized until registered on the raw definition. Fix: `HtmlSanitizer` now sets
  `HTML.DefinitionID`/`HTML.DefinitionRev` and calls
  `$config->maybeGetRawHTMLDefinition()` to `addAttribute($tag, $attribute, 'Text')`
  for every `data-*` name in `RichTextFields::ALLOWED_ATTRIBUTES` (currently
  `data-type`, `data-checked`, `data-callout-type`). **Anyone adding a new `data-*`
  attribute to `ALLOWED_ATTRIBUTES` must bump `HTML.DefinitionRev`**, or HTMLPurifier
  will keep serving the stale cached definition from `storage/app/htmlpurifier`
  instead of picking up the new attribute (a green test suite would not catch this if
  the on-disk cache from a previous run survives — tests here ran against a cleared
  cache each time to confirm behavior, not just to be tidy).
- **Existing `HtmlSanitizerTest::test_it_removes_images_and_event_handlers` assumed
  `<img>` was always stripped** (true before this task, per `documentation/rich-text.md`'s
  now-outdated "no `<img>`" line — task 08 updates that doc). Updated the test
  (renamed `test_it_keeps_images_but_strips_event_handlers`) to assert the new
  correct behavior: the tag and its allow-listed attributes survive, `onerror` does
  not.

## Task 02 — backend Markdown-converter consistency

- No deviations: implemented exactly as scoped — `ValidMarkdown` swapped
  `CommonMarkConverter` for `GithubFlavoredMarkdownConverter` (drop-in constructor
  change), `EpubExporter::converter()` gained `StrikethroughExtension` and
  `TaskListExtension` alongside its existing `SmartPunctExtension`, added to its own
  isolated converter instance (never shared with `Scene::renderedContents()`).
- No new issues hit during implementation or verification — this was a narrow,
  well-scoped converter swap/addition with no sanitizer or allow-list interaction.
- New `tests/Unit/Rules/ValidMarkdownTest.php` (no prior file existed) covers plain
  Markdown, strikethrough syntax, and GFM task-list syntax all passing validation.
  `tests/Unit/Services/EpubExporterTest.php` gained
  `test_strikethrough_and_task_list_render_as_real_markup_in_the_epub`, confirming
  `~~struck~~` renders as `<del>struck</del>` and `- [ ]`/`- [x]` render as real
  `type="checkbox"` elements (one checked, one not) rather than literal tildes/brackets.
  Full suite: 712 tests / 2785 assertions passing; `composer lint` clean. No
  frontend/runtime surface was touched by this task, so no browser/build verification
  was needed.

## Task 03 — extension wiring + vitest + round-trip tests

- **Deviation: extracted `buildExtensions(format, { placeholder, onLink })`** out of
  `registerWysiwyg()`'s `init()` into its own exported function in `wysiwyg.js`,
  rather than hand-duplicating the extensions array in the test file. Not scoped
  explicitly by the task file, but required to satisfy `testing.md`'s own instruction
  that the vitest suite use "the same extensions array wysiwyg.js builds" without
  drift — a copy would silently go stale on the next config change. `init()` now
  just calls it.
- **`@tiptap/extension-table`'s import shape resolved**: single package, named
  exports (`Table`, `TableRow`, `TableHeader`, `TableCell`), no subpath imports
  needed — confirmed via `node -e "console.log(Object.keys(require(...)))"` and the
  package's own `exports` map (which does expose `./table`, `./cell`, etc. subpaths,
  but the root import already re-exports everything, so the simpler form is used).
  `@tiptap/extension-image`'s default export is `Image`.
- **Issue → resolution: `Editor.getHTML()` needs `window.document`.**
  `getMarkdown()` works in plain Node, but `getHTML()` calls ProseMirror's
  `DOMSerializer`, which reaches for `window.document` — throws
  `ReferenceError: window is not defined` under vitest's default `node` environment.
  Fix: added `vitest.config.js` with `test.environment: 'jsdom'` (new devDependency
  `jsdom`) and `test.include: ['resources/js/**/*.test.js']`. This is a
  suite-wide default now, not a per-file `@vitest-environment` comment — every future
  co-located test file gets jsdom automatically.
- **Deviation: `label`/`input`/`span`/`div`, `data-type`/`data-checked`, etc. were
  already added to `RichTextFields` by task 01** (confirmed by reading the file
  before starting) — no allow-list change was needed in this task, since the
  extension config's actual `renderHTML` output for `TaskItem`/`TaskList` matches
  what task 01 anticipated exactly.
- **Issue spotted, deliberately deferred to task 04 (not fixed here): the Table
  extension's real HTML output includes more than `RichTextFields::ALLOWED_TAGS`/
  `ALLOWED_ATTRIBUTES` currently allow.** Observed directly (not assumed) via a
  probe script: `Table`'s default `renderHTML` emits a `style="min-width: …"`
  attribute on `<table>`, a `<colgroup>` wrapping `<col style="min-width: …">`
  elements, and `colspan`/`rowspan` attributes on every `<td>`/`<th>` — none of
  which are in the current allow-list (`colgroup`/`col` aren't tags at all yet;
  `style` is deliberately excluded project-wide as presentational; `colspan`/
  `rowspan` aren't in `ALLOWED_ATTRIBUTES`). Consequence: today, `HtmlSanitizer`
  would strip all of that from a saved HTML-mode table — including `colspan`,
  which means **a merged cell would currently NOT survive HTML-mode round-trip
  through the server**, contradicting spec.md's stated "survives in html format"
  claim for that gap. This task's own vitest tests don't catch it because they
  exercise the Tiptap `Editor` directly and never call `HtmlSanitizer`. Left
  unfixed here deliberately: task 03 has no toolbar/slash-menu entry that lets a
  user create a table yet (task 04 does), so per the plan's own invariant ("any
  task adding a new toolbar/slash entry must widen the allow-list in the same
  task, not a later one"), the allow-list widening for `colgroup`/`col`/
  `colspan`/`rowspan` (and a decision on whether `style` needs a narrow,
  value-restricted exception for column-width styling, or whether the table
  should be configured to not emit `style` at all) belongs to task 04, not this
  one. **Flagging explicitly so task 04 doesn't rediscover this from a failing
  round-trip test late.**
- New `resources/js/wysiwyg.test.js` (new file, first vitest consumer in the
  project): 13 tests covering table/image/task-list round-trips in both `html`
  and `markdown` format, the documented merged-cell and resized-image losses
  (hand-constructed via HTML input, since the editor's own UI can't produce either
  yet), the three documented cosmetic normalisations (`_em_` → `*em*`,
  reference-link → inline, bullet-marker change), and regression guards for
  already-safe nested blockquotes and hard line breaks. `npm run test` (13/13
  passing).
- No PHP files were touched by this task; `composer test` (712/712) and
  `composer lint -- --test` both stayed green, confirming this task didn't
  regress anything task 01/02 already covered.
- Verified in a real browser (`run-imagoldfish` skill): built assets, logged in as
  the seeded dev user, opened `scenes/1/edit` (which mounts three editor
  instances — one markdown-mode `contents` field, two html-mode `description`/
  `notes` fields), confirmed all three mount without console errors, and typed a
  new paragraph into the markdown-mode editor to confirm editing and
  textarea-sync still work with the widened extension set. Table/image/task-list
  UI affordances aren't visible yet (no toolbar/slash entries — task 04), so this
  run only confirms the wiring doesn't break the existing editing surface.

## Task 04 — table & image UI (toolbar, slash menu, resize, merge/split)

- **Deviation: resolved task 03's flagged issue by overriding `Table`'s
  `renderHTML` instead of widening the allow-list to accept `style`/`colgroup`/
  `col`.** Reading `@tiptap/extension-table`'s installed source
  (`dist/table/index.js`) confirmed the resolution-log prediction exactly:
  `Table.renderHTML()` unconditionally emits `style="min-width: …px"` on `<table>`
  and a `<colgroup>`/`<col style="…">` pair, regardless of whether column-resize is
  enabled (that flag only gates the ProseMirror plugin, not the static
  `renderHTML`/`toDOM` output used by `getHTML()`/save). Rather than add a `style`
  exception to `RichTextFields::ALLOWED_ATTRIBUTES` — which the docblock and this
  project's convention explicitly rule out ("no presentational attributes... on any
  tag") — `wysiwyg.js` now exports a `PlainTable = Table.extend({ renderHTML })`
  that drops the `style` merge and the `colgroup` child entirely, keeping only
  `colspan`/`rowspan` on `<td>`/`<th>` (untouched — those come from
  `TableCell`/`TableHeader`, not overridden). This only changes the *serialized*
  output (`getHTML()`); the live in-editor column-width node view (`TableView`,
  via `addNodeView`) is a separate DOM path and still renders normally while
  editing. Verified directly: inserting a table via the toolbar and saving/
  reloading through the real server produced byte-identical `<table>` markup with
  no `style=`/`<colgroup`/`<col ` anywhere, confirmed both by a vitest case and a
  live `run-imagoldfish` round trip through `HtmlSanitizer`.
- **`RichTextFields::ALLOWED_ATTRIBUTES` gained `td`/`th` → `['colspan',
  'rowspan']`** (not flagged as needed in the task file's own text, which only
  called out re-verifying `img`'s `width`/`height` — those were already present
  from task 01, confirmed, no change needed there). Added anyway because task 03's
  resolution-log entry explicitly named this as the residual gap blocking merged-
  cell round-trip and asked task 04 to close it. No `HtmlSanitizer.php` change was
  needed: unlike the `data-*` attributes task 03 had to register on HTMLPurifier's
  raw definition, `colspan`/`rowspan` are standard HTML4 `td`/`th` attributes
  already recognized by HTMLPurifier's core Tables module, so `HTML.Allowed`
  alone grants them — confirmed via a new `HtmlSanitizerTest` case
  (`test_it_preserves_a_merged_table_cell`) that also asserts a stray `style`/
  `colgroup` fed to the sanitizer directly (simulating a non-editor source) is
  still stripped, since the editor itself never emits it.
- **Image resize option shape**: `@tiptap/extension-image`'s `resize` option is
  not a boolean — it's `false` (default, off) or an object
  `{ enabled, directions, minWidth, minHeight, alwaysPreserveAspectRatio }`
  (confirmed by reading the installed source). `buildExtensions()` now configures
  `resize: isMarkdown ? false : { enabled: true }`; the unset fields fall back to
  `ResizableNodeView`'s own internal defaults (confirmed by reading
  `@tiptap/core`'s `ResizableNodeView` constructor), so no need to hand-specify
  `directions`/`minWidth`/etc.
- **`setImage()` Alpine method mirrors `setLink()`'s two-part shape**: a
  `window.prompt` for the http/https URL (same regex guard as `setLink`, silently
  no-ops on a non-http(s) URL or a cancelled/empty prompt) followed by a second
  `window.prompt` for optional alt text, then
  `editor.chain().focus().setImage({ src, alt }).run()` (the `setImage` command
  ships built into `@tiptap/extension-image`, no custom command needed).
- **`buildSlashItems` and `slashExtension` both gained an `onImage` parameter**
  (alongside the existing `onLink`), and `buildSlashItems` is now exported
  (alongside `buildExtensions`) purely so `wysiwyg.test.js` can assert on the
  per-format item list directly (e.g. "no slash entry ever inserts merge/split")
  without re-deriving it from a live editor's suggestion plugin.
- **Toolbar layout**: Table and Image buttons sit in their own section after the
  existing Link/Horizontal-rule buttons (same tier — both take arguments/prompts,
  matching `ui.md`'s guidance), followed by a `@if (! $markdown)`-gated pair of
  Merge-cells/Split-cell buttons — the one new `isMarkdown`-style conditional this
  task introduces, distinct from (and not to be confused with) the suppression
  task 05 removes for Underline/Strike. Task list joined the plain `$toggles`
  array next to Bulleted/Numbered list, no format gate needed (round-trips in
  both formats since task 03).
- **Glyphs used** (all inline text/HTML-entity, no new asset, per CLAUDE.md/
  `ui.md`): `&#9744;` (☐) for Task list, `&#9638;` (▦) for Table, `&#128247;` (📷)
  for Image, `&#8676;&#8677;`/`&#8677;&#8676;` for Merge/Split cells.
- **CSS additions** (`resources/css/app.css`, appended to the existing `.tiptap`
  block): table borders/cell padding/header shading (ProseMirror ships none by
  default and `prose` only styles rendered, non-editing output), a
  `.selectedCell` background for ProseMirror's own cell-selection class, and
  `taskList`/`taskItem` flex layout + `list-style: none` so the checkbox and its
  content align instead of showing a bullet marker.
- No PHP controller/route/policy surface touched, per the plan's own invariant —
  this task is entirely editor configuration, one Blade component, and the two
  `RichTextFields`/`HtmlSanitizerTest` additions above.
- New/changed tests: `resources/js/wysiwyg.test.js` gained four new `describe`
  blocks (image resize by format, table merge/split command availability +
  the style/colgroup-free merged-cell round-trip, and two slash-menu-shape
  regression guards) — 19/19 passing (up from 13). `tests/Unit/HtmlSanitizerTest.php`
  gained `test_it_preserves_a_merged_table_cell` and widened
  `test_purifier_allowed_html_lists_the_new_tags_and_attributes` to check
  `td[colspan|rowspan]`/`th[colspan|rowspan]` — full suite 713/713 (up from 712),
  `composer lint -- --test` clean.
- Verified in a real browser (`run-imagoldfish` skill): built assets, logged in as
  the seeded dev user, opened `scenes/1/edit`. Confirmed, per field: (1) the
  Table button inserts a real 3×3 bordered/shaded table in both the HTML-mode
  `description` field and the Markdown-mode `contents` field (serializing to a
  valid GFM pipe-table there); (2) the Image button prompts for a URL/alt text
  and inserts a real `<img>` into the document model (verified via the synced
  textarea value; the image itself renders invisible in this sandbox only
  because the URL doesn't resolve and `Image`'s resize node view hides the
  element until `onload`, an upstream library behavior, not a bug in this
  feature); (3) the Task-list toolbar button toggles a real checkbox item,
  editable and saved; (4) Merge-cells/Split-cell buttons render only on the two
  HTML-mode fields (`description`/`notes`, count 2) and are entirely absent from
  the Markdown-mode `contents` field (count 0), confirmed via
  `document.querySelectorAll('button[title="Merge cells"]').length`; (5) saving
  a table + task list through "Save and stay" and reloading the edit page shows
  byte-identical HTML back (no `style`/`colgroup` introduced, `colspan`/`rowspan`
  preserved) — the full round trip through `HtmlSanitizer` and back, not just a
  vitest-level check. `WysiwygFormTest` run unmodified, still green (0 new
  assertions, per the task file and `testing.md`).

## Task 05 — Underline & Strikethrough in Markdown mode

- **`@tiptap/markdown`'s real mark-config API has no `{ tag: 'u' }` shorthand** —
  contrary to architecture.md's speculative sketch, `parseMarkdown`/`renderMarkdown`
  are always functions (confirmed by reading the installed
  `@tiptap/markdown/src/MarkdownManager.ts`). More importantly, **no `parseMarkdown`
  override was needed for the input side at all**: raw inline HTML like `<u>text</u>`
  never reaches the mark's `parseMarkdown`/tokenizer path — `marked` tokenizes it as
  an `'html'` token, which `MarkdownManager.parseHTMLToken()` resolves via
  `generateJSON()` using the mark's ordinary ProseMirror-level `parseHTML()` (`{ tag:
  'u' }`, already present on `@tiptap/extension-underline`'s stock `Underline`,
  unmodified). This is the exact mechanism spec.md called "CommonMark's raw-inline-HTML
  passthrough" and the reason no `ValidMarkdown` change was needed for it — confirmed
  by reading the source, not assumed.
- **`renderMarkdown` needed overriding, and so did `markdownTokenizer`.**
  `@tiptap/extension-underline`'s stock mark ships its own non-standard `++text++`
  Markdown dialect (a custom `renderMarkdown` and a custom marked tokenizer keyed off
  `++`) — neither decided-upon nor wanted, since spec.md names `<u>` passthrough as
  the *one* sanctioned exception. `wysiwyg.js` now exports `MarkdownUnderline =
  Underline.extend({ markdownTokenizer: null, renderMarkdown(node, helpers) { return
  `<u>${helpers.renderChildren(node)}</u>`; } })`. `markdownTokenizer: null` (not
  `undefined`) is required to actually disable it — `@tiptap/core`'s
  `getExtensionField()` falls back to the parent extension's field whenever the
  child's own field is `=== undefined`, so an explicit `undefined` override would
  silently keep inheriting the `++` tokenizer; only a non-`undefined` falsy value
  (here `null`) short-circuits `registerExtension()`'s `if (tokenizer && ...)` check.
  This is a general trap for any future `.extend()` override meant to *disable* an
  inherited Tiptap field, not just this one.
- **`MarkdownUnderline` replaces StarterKit's stock `Underline` unconditionally in
  both formats**, not just under an `isMarkdown` branch. `underline: false` is now
  always passed to `StarterKit.configure()`, and `MarkdownUnderline` is always pushed
  into the shared `extensions` array (same "always the plain/custom variant" pattern
  already used for `PlainTable` vs. `Table`). This is safe for HTML-mode fields too:
  `renderMarkdown`/`markdownTokenizer` are inert there (`extensions.push(Markdown)`
  only happens for markdown-format editors), and `renderHTML`/`parseHTML` are
  untouched, inherited from the stock mark.
- **`@tiptap/extension-underline` added as an explicit `package.json` dependency**
  (was previously only present transitively via `@tiptap/starter-kit`) — this task is
  the first to `import` from it directly, matching the existing convention of listing
  every directly-imported `@tiptap/*` package (see `extension-table`/`extension-image`
  from tasks 03/04). `npm install` confirmed no lockfile drift (the resolved version
  was already present at the correct range).
- **Slash menu's `mdHide` mechanism has no remaining users.** Removing `mdHide: true`
  from the `'Underline'`/`'Strikethrough'` entries left `buildSlashItems()`'s
  `format === 'markdown' ? items.filter(...) : items` ternary filtering on a flag no
  item sets any more — simplified to a plain `return items;` with a comment
  explaining why no entry needs format-gating (KISS: dead conditional removed rather
  than left as a no-op for a future entry that may never need it).
- **No PHP change needed.** Strikethrough already round-trips as plain GFM (task 02);
  Underline's `<u>` passthrough needs no `ValidMarkdown`/sanitizer change since it's
  literal HTML `ValidMarkdown`'s CommonMark parser already tolerates as inert
  raw-HTML passthrough — confirmed via the existing `tests/Unit/Rules/ValidMarkdownTest.php`
  from task 02 continuing to pass unmodified.
- New tests in `resources/js/wysiwyg.test.js` (3 added, 22/22 passing overall, up
  from 19): a `<u>text</u>` round-trip, a `~~text~~` round-trip (both via
  hydrate → `getMarkdown()` → re-hydrate unchanged), and a regression guard
  asserting the markdown-format slash menu's item list includes `'Underline'`/
  `'Strikethrough'` (guards against `mdHide` reappearing). `composer test`
  713/713, `composer lint -- --test` clean — no PHP files were touched by this
  task besides doc comments, so this also confirms tasks 01–04 weren't regressed.
- Verified in a real browser (`run-imagoldfish` skill): built assets, logged in as
  the seeded dev user, opened `scenes/1/edit`. Confirmed the Markdown-mode
  "Contents (Markdown)" field's toolbar now shows Underline (U) and Strikethrough
  (S) buttons (previously `@if (! $markdown)`-gated, HTML-mode fields only).
  Selected a word in the live editor, clicked Underline then Strikethrough, and
  read the synced hidden textarea directly: it contained `~~<u>wher</u>~~e` —
  confirming both marks serialize to their documented Markdown forms in the real
  running app, not just in vitest. No console errors on page load or after
  toggling. Did not verify the full save → reload round trip in the browser (the
  "Save and stay" click could not be reliably automated in this pass — button
  locator/session state issue in the browser driver, unrelated to the feature);
  the save-then-reload path is otherwise already covered by
  `tests/Feature/RichTextRenderingTest.php` and `WysiwygFormTest` server-side.

## Task 06 — Callout / alert block node

- The implementation (custom `Callout` node in `resources/js/wysiwyg.js`,
  `RichTextFields::ALLOWED_ATTRIBUTES['blockquote'] = ['data-callout-type']`,
  toolbar/slash-menu wiring in `wysiwyg.blade.php`/`buildSlashItems()`, CSS in
  `resources/css/app.css`, and the corresponding `wysiwyg.test.js`/
  `HtmlSanitizerTest` cases) was already present in the working tree at the
  start of this task run — apparently completed in an earlier session that
  did not finish the verification/move/log steps. This entry records the
  verification performed now, not new implementation work; no code was
  changed for this task.
- **Design as built, confirmed by reading the source**: `Callout` is a sibling
  `Node` of StarterKit's `Blockquote` (`priority: 200`, `group: 'block'`,
  `markdownTokenName: 'blockquote'`), so a plain blockquote with no `[!TYPE]`
  marker is untouched. `parseMarkdown` matches only a first paragraph line
  that is *exactly* `[!TYPE]` (via `CALLOUT_MARKER`, trailing whitespace
  allowed, nothing else on the line — matching GitHub's own rule, and
  regression-guarded by a dedicated test: `[!NOTE] text` sharing a line is
  NOT a callout). `renderMarkdown` re-emits `> [!TYPE]` + `> `-prefixed body,
  confirmed byte-exact in the live app (see verification below). HTML mode
  presents over the existing `<blockquote>` via `data-callout-type`
  (`parseHTML` priority 60, above Blockquote's default 50, so an attributed
  blockquote resolves to `Callout` and a bare one stays `Blockquote`).
  Toolbar/slash menu are unconditional in both formats (not `isMarkdown`-gated
  — callouts are decided-safe in both, per the task's key decision). The
  toolbar button (`toggleCallout()` in `wysiwyg.js`) inserts a `note` callout
  when the cursor isn't in one, otherwise cycles the existing callout through
  the five types in place — the simplest type-picker fitting the existing
  glyph-button toolbar language, chosen over a dropdown/prompt per the task
  file's own "implementer's call" latitude.
- **Verification performed this task run**:
  - `npm run test` (vitest): 29/29 passing, including the task's own six
    callout cases in `resources/js/wysiwyg.test.js` (hydration into a callout
    node vs. plain blockquote, all five types, the same-line-marker
    regression guard, HTML-mode `data-callout-type` round-trip, and the
    slash-menu-entry presence check).
  - `composer test`: 713/713 passing, including
    `HtmlSanitizerTest::test_it_preserves_a_callout_blockquote_attribute` and
    the widened `purifierAllowedHtml()` assertion covering
    `blockquote[data-callout-type]`.
  - `composer lint -- --test`: clean.
  - `npm run build`: succeeded; confirmed no stale `public/hot`.
  - Live browser verification (`run-imagoldfish` skill): built assets, logged
    in as the seeded dev user, opened `scenes/1/edit` (three editor
    instances — markdown-mode `contents`, HTML-mode `description`/`notes`).
    In the markdown-mode field: clicked the Callout toolbar button with the
    cursor in the first paragraph and read the synced textarea directly — it
    now began `> [!NOTE]\n> Long ago in the forest of Brittany…` (the marker
    line plus `> `-prefixed body, exactly the documented convention); clicking
    the same button again (cycling) changed it to `> [!TIP]` with the body
    unchanged, confirming `toggleCalloutType`'s in-place cycling. In the
    HTML-mode `description` field: clicked the Callout button and read the
    synced textarea — it contained a real
    `<blockquote data-callout-type="note"><p></p></blockquote>` inserted at
    the cursor. Both fields' rendered output showed a blue-bordered box with
    an info icon and a "NOTE" label — matching this repo's own
    `documentation/*.md` GFM-alert visual convention, per the task's
    instruction to mirror it rather than invent a new visual language. No
    console errors in either field during insertion or cycling.
- **Deviation/gap noted, not fixed in this task**: `documentation/rich-text.md`
  was not updated to mention callouts — that update is explicitly task 08's
  responsibility (`08-docs-and-regression.md` scopes the allow-list-table and
  "Two modes" doc edits), so it was left alone here per the plan's own task
  boundaries, not an oversight.

## Task 07 — Fallback-warning structural-check list

- **New standalone module**: `resources/js/wysiwyg/fallbackChecks.js`, exporting
  `hasMergedTableCell(doc)`, `hasResizedImage(doc)`,
  `hasUnmatchedHtmlWrapperTag(source, editor)`, and the combined
  `findFallbackWarnings({ editor, source })` aggregate (returns an array of
  warning keys, e.g. `['mergedTableCell', 'unmatchedHtmlWrapperTag']`). Imports
  nothing from `wysiwyg.js` — purely standalone, per the task's own requirement
  — so `autosave-with-revisions` can depend on this one file without pulling in
  toolbar/Alpine code.
- **Issue → resolution: checks 1/2 operate on `editor.getJSON()`, but check 3
  cannot — by design, not oversight.** Initially assumed all three checks could
  run against the same parsed ProseMirror document. Reading
  `@tiptap/markdown`'s `parseHTMLToken`/`generateJSON` path (confirmed live via
  a probe, not just the doc) showed an unmatched wrapper tag (e.g.
  `<div class="letter">`) is unwrapped and discarded *during* parsing —
  ProseMirror keeps only the recognized content inside it. By the time there is
  a parsed document to inspect, the wrapper is already gone; there is nothing
  left to detect. So check 3 (`hasUnmatchedHtmlWrapperTag`) takes the **raw
  source string** plus the editor (for its schema), not `getJSON()` — the only
  one of the three checks with this shape. This is stated explicitly in the
  function's doc comment so a future caller doesn't try to pass it a parsed
  doc and wonder why it never fires.
- **Issue → resolution: a naive "any unmatched tag anywhere in source" scan
  produced false positives on already-safe constructs.** First attempt used a
  regex to find every opening HTML tag in the source and flagged any whose base
  tag name wasn't claimed by some node's own `parseHTML` rule. This flagged two
  cases it must not: a plain table's own `<tbody>` (no dedicated ProseMirror
  node — only `table`/`tr`/`td`/`th` have their own rules) and a task list's own
  rendered `<label>`/`<input>`/`<span>`/`<div>` (only the outer
  `<li data-type="taskItem">`/`<ul data-type="taskList">` have rules — the rest
  are that node's own internal rendering, never expected to have their own
  schema rule). Root cause: those inner tags are *never meant to be
  independently recognized* — they're structural implementation detail of an
  already-matched ancestor, exactly the same "unwrap the tag, keep the content"
  mechanism as the genuine unmatched-wrapper case, just happening on purpose
  instead of by accident. Fix: rewrote the check to use a real DOM parse
  (`window.DOMParser`, available via the `jsdom` vitest environment already
  configured for this suite) and only examine the **outermost** elements of the
  source — once an element matches a registered `parseHTML` selector (via
  `Element.matches()`), its children are never independently examined, so
  `<tbody>`/`<tr>`/`<td>` inside a matched `<table>` and
  `<label>`/`<input>`/`<span>`/`<div>` inside a matched
  `<li data-type="taskItem">` are correctly never flagged, while a genuinely
  unmatched top-level `<div class="letter">` still is. A green test suite would
  not have caught the first (regex) version's false positives without the
  specific "does not flag a table/task-list" tests this task added — a
  narrower test set (only testing the div-wrapper positive case) would have
  shipped the bug.
- **Selector source, not a hand-maintained tag list**: `registeredSelectors()`
  reads `editor.schema.nodes`/`.marks`' `spec.parseDOM` directly (populated by
  Tiptap from each extension's own `parseHTML()`), so the recognized set can
  never drift from what `buildExtensions()` (`wysiwyg.js`) actually registers —
  no second list to keep in sync as future tasks add nodes.
- **No DOM environment fallback**: mirroring `@tiptap/markdown`'s own guard for
  the same constraint, `hasUnmatchedHtmlWrapperTag` returns `false` (no
  warning) when `window`/`window.DOMParser` isn't available, rather than
  guessing. Not expected to matter in practice (this module always runs
  alongside a live Tiptap `Editor`, which itself needs a DOM), but kept
  defensive since the module accepts a bare `source` string that could
  theoretically arrive without one.
- New `resources/js/wysiwyg/fallbackChecks.test.js` (18 tests): each check's
  positive case (a real merged-cell table via `colspan`/`rowspan`, a real
  width/height-bearing image, spec.md's own `<div class="letter">` example) and
  negative cases (plain table/image/task-list, an underline mark, a callout
  block in both formats, and all three of task 03's cosmetic-normalisation
  cases — `_em_` → `*em*`, reference-link → inline, bullet-marker change); plus
  the combined aggregate returning nothing, one case, and — for a document
  wrapping a merged-cell table in an unmatched `<div>` — both cases at once.
  `npm run test`: 47/47 passing (up from 29; the 18 new here plus the 29
  already in `wysiwyg.test.js`, unmodified).
- No PHP files were touched by this task; `composer test` (713/713) and
  `composer lint -- --test` both stayed green, confirming tasks 01-06 weren't
  regressed. `npm run build` succeeded (font-resolution warnings are
  pre-existing and unrelated to this change).
- **No runtime/browser verification performed** — this task explicitly builds
  no UI (per the task file: "This task does not build any UI... This task's
  output is a pure function"), and `fallbackChecks.js` is not imported by
  `wysiwyg.js`, any Blade view, or the app's Vite entry point, so there is no
  rendered surface to observe yet. It will get its first real caller when
  `autosave-with-revisions` wires it in.

## Task 08 — Documentation & regression sweep

- **Doc updates** (`documentation/rich-text.md`): rewrote the allow-list section to
  list the tags/attributes `expand-tip-tap` added (tables, `img`,
  `label`/`input`/`span`/`div`, and the full `ALLOWED_ATTRIBUTES` map incl.
  `colspan`/`rowspan`/`data-callout-type`); rewrote the "Two modes" sentence — Underline
  and Strikethrough are now enabled (not disabled) in Markdown mode, with table
  merge/split and image resize called out as the two things that stay HTML-mode-only;
  refreshed the stale "Image upload deferred to v2" callout (the toolbar now inserts an
  `<img>` reference to an existing URL — only *uploading a new file* is still deferred);
  added a short "Fallback-warning structural checks" section pointing at
  `resources/js/wysiwyg/fallbackChecks.js` and naming `autosave-with-revisions` §11.5.2
  as its consumer; added a short callout-node paragraph under "Toolbar + slash menu"
  (task 06 explicitly deferred this doc mention to this task). `app/Support/RichTextFields.php`'s
  docblock fixed per `spec.md`'s "Loose end spotted while writing this": `Scene.contents`
  is never routed through the sanitizer, but it **is** edited through `x-wysiwyg` (in
  `markdown` mode) — the stale "or the editor" half of the old claim is removed.
- **`CLAUDE.md`**: added `npm run test` (vitest) to the Commands section, per
  `autosave-with-revisions` §9.12's note that whoever adds vitest first documents it —
  this plan (task 03) was that feature.
- **New import-path regression test**: `tests/Unit/Import/ContentSanitizerTest::test_gfm_table_image_and_task_list_markdown_passes`,
  asserting a Markdown document with a GFM table/image/task-list now passes
  `assertMarkdownAllowed()` — before task 01's allow-list widening it would have been
  rejected as "disallowed HTML content" once its rendered `<table>`/`<img>`/task-list
  markup hit the old, narrower `RichTextFields::ALLOWED_TAGS`.

### Issues → resolutions

- **The new regression test initially failed for a reason unrelated to the allow-list
  widening it was meant to confirm.** `Str::markdown()`'s GFM task-list renderer emits
  `<input disabled type="checkbox">` for a rendered (read-only) checkbox — `disabled`
  was not yet in `RichTextFields::ALLOWED_ATTRIBUTES['input']` (only `type`/`checked`
  were). Root cause: TipTap's own `TaskItem` HTML output (what the HTML-mode fields'
  sanitizer allow-list was designed against) never sets `disabled` — only GFM's static
  Markdown-rendering path does, for `Scene.contents`'s import check. Fix: added
  `disabled` to `ALLOWED_ATTRIBUTES['input']` (not a security-relevant attribute either
  way; documented in the constant's inline comment so a future reader isn't confused by
  the "TipTap never emits this" observation).
- **A second, separate issue surfaced after that fix, still on the same test**:
  `ContentSanitizer::canonicalize()`'s before/after string comparison false-positived on
  *any* Markdown `<input>` regardless of the allow-list, because HTMLPurifier applies
  structural normalizations to `<input>` that have nothing to do with stripping
  disallowed content: (1) it always self-closes void elements (`/>` vs GFM's `>`), (2)
  `HTMLPurifier_AttrTransform_Input` (confirmed by reading
  `vendor/ezyang/htmlpurifier/library/HTMLPurifier/AttrTransform/Input.php`) force-adds
  an empty `value=""` to every checkbox/radio `<input>` lacking one, and (3) it
  rewrites boolean attributes from GFM's `checked=""`/`disabled=""` shorthand to
  `checked="checked"`/`disabled="disabled"`. None of these are content the sanitizer
  removed — they're serialization-only differences a plain string diff can't tell apart
  from a real strip. Fix: `canonicalize()` now also normalizes self-closing void
  elements, strips HTMLPurifier's auto-added empty `value=""`, and collapses
  `checked`/`disabled`'s boolean-attribute serialization to a bare form, before
  comparing either side. **A green test suite would not have caught this before this
  task** — no prior test exercised a Markdown document containing an `<input>` element
  through `ContentSanitizer::assertMarkdownAllowed()`.
- **Full-suite sweep**: `composer test` 714/714 (up from 713 — the one new test),
  `composer lint -- --test` clean, `npm run test` 47/47 (unchanged from task 07 — no JS
  files were touched by this task), `npm run build` succeeded (pre-existing
  font-resolution warnings only, unrelated). `tests/Feature/WysiwygFormTest.php` run in
  isolation: 3/3 passing, confirming the progressive-enhancement `<textarea>` contract
  needed no changes across every prior task's toolbar/Blade edits. No browser/runtime
  verification was performed for this task specifically — it touched no Blade/JS/CSS,
  only PHP (`ContentSanitizer`, `RichTextFields` docblock/attribute) and Markdown docs.
