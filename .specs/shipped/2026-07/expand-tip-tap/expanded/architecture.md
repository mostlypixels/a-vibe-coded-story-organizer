---
title: Expand Tip Tap — Architecture
---

# Architecture

No new controllers, routes, migrations, or policies — this feature is entirely
**editor configuration** (`resources/js/wysiwyg.js`) plus **allow-list widening**
(`app/Support/RichTextFields.php`) plus **converter consistency**
(`app/Rules/ValidMarkdown.php`, `app/Services/EpubExporter.php`,
`Scene::renderedContents()`). The existing three-layer shape
(editor → sanitizer allow-list → render path) does not change; this spec only widens
what each layer permits.

## Package changes (`package.json`)

Add, pinned to the project's existing `@tiptap/*` version (`3.27.1`):

* `@tiptap/extension-table` — ships `Table`, `TableRow`, `TableHeader`, `TableCell`.
  Verified via tarball inspection: `parseMarkdown`/`renderMarkdown` are real, no
  `markdownTokenName`/`priority` needed (both default correctly).
* `@tiptap/extension-image` — ships `Image` with real `parseMarkdown`/`renderMarkdown`.

**No new package for task lists.** `@tiptap/extension-list` is **already installed**
(a `starter-kit`/other-extension dependency today) and already exports `TaskItem` and
`TaskList` with full Markdown handlers — confirmed via
`node -e "console.log(Object.keys(require('@tiptap/extension-list')))"` in this repo,
which lists both. Do not add `@tiptap/extension-task-item`/`@tiptap/extension-task-list`
— those are separate, thinner re-export shim packages; importing from the wrong one is
an easy mistake worth calling out in the PR/commit that does this.

No PHP composer changes: `league/commonmark ^2.8` (already installed) bundles
`League\CommonMark\Extension\TaskList` and `League\CommonMark\Extension\Strikethrough`
— both confirmed present under `vendor/league/commonmark/src/Extension/`. Only
*enabling* them is new work.

## `resources/js/wysiwyg.js` changes

All changes are inside `registerWysiwyg()`'s `init()`, where the `extensions` array is
built, plus `buildSlashItems()` and the toolbar-adjacent config.

1. **Table + Image extensions**, added to the shared `extensions` array (both apply to
   both `html` and `markdown` format — round-trip support is symmetric):
   ```js
   import Table from '@tiptap/extension-table';
   import TableRow from '@tiptap/extension-table/table-row'; // or named export, confirm at implementation time
   import TableHeader from '@tiptap/extension-table/table-header';
   import TableCell from '@tiptap/extension-table/table-cell';
   import Image from '@tiptap/extension-image';
   import { TaskItem, TaskList } from '@tiptap/extension-list';
   ```
   Confirm the exact `extension-table` sub-export shape once installed (single package
   with named exports vs. subpath exports) — the spec's own verification pulled the
   tarball but did not need to resolve import ergonomics, only handler presence.

2. **Prevent, don't just warn, for Markdown-format fields** (the `isMarkdown` branch
   that already turns off `strike`/`underline` in StarterKit's config):
   * `Table.configure({ resizable: false })` is not what's being restricted here — it's
     `mergeCells`/`splitCell`. Those are *commands*, not a configure-time flag, so the
     prevention point is the **toolbar and slash-menu item list**, same as every other
     `mdHide` entry: don't add a merge/split entry to `buildSlashItems()`/the Blade
     toolbar for Markdown-format fields in the first place. There is currently no
     merge/split UI at all (StarterKit ships no table), so this is "don't add it for
     markdown mode" rather than "remove an existing entry."
   * `Image.configure({ inline: false })` — leave `resize` unset/false (its default) so
     no resize handles render; this is the extension's own opt-in `resize` option,
     already off by default, so the Markdown-mode branch simply never turns it on. The
     HTML-mode config *may* enable resize (a product decision left to whoever implements
     this — not required by this spec, since Non-goals excludes redesigning the toolbar
     beyond what's needed for round-trip parity).

3. **Task lists**: add `TaskItem`/`TaskList` to the shared `extensions` array
   unconditionally — they're decided-supported in both formats, same tier as
   tables/images, no `isMarkdown` gate needed on the extension itself (only the toolbar
   entry visibility, which already exists generically for all list types).

4. **Underline round-trip in Markdown mode.** Two independent changes, both required —
   installing the custom serializer alone does nothing if the mark stays disabled:
   * Remove `underline: false` from the `isMarkdown ? { strike: false, underline: false } : {}`
     StarterKit override — `Strike` also comes off this list per the Strikethrough
     decision below, so this whole conditional override goes away entirely once both
     decisions land (StarterKit's own `Underline`/`Strike` are fine for HTML mode; the
     `isMarkdown` branch that suppressed both becomes empty and can be deleted, per
     `spec.md`'s "Underline" section: *"Both need to go for Markdown-format fields"*).
   * Give `Underline` a custom Markdown handler. `@tiptap/extension-underline`'s
     `Underline` mark ships no `parseMarkdown`/`renderMarkdown` of its own (it's not one
     of the two verified-modest nodes) — extend it:
     ```js
     const MarkdownUnderline = Underline.extend({
       parseMarkdown: { tag: 'u' }, // reads literal <u>…</u> via CommonMark's raw-HTML passthrough
       renderMarkdown: { tag: 'u' }, // or an explicit render function — confirm the extension's actual config shape against @tiptap/markdown's node/mark config API at implementation time
     });
     ```
     The exact config shape (`{tag}` object vs. a render function) should be confirmed
     against `@tiptap/markdown`'s `MarkdownManager` API when this is implemented — the
     spec only established *that* a custom pair is needed and *what* it must emit
     (`<u>text</u>`), not the literal call signature.
   * A one-line comment at this extension's config explaining *why* `<u>` specifically
     gets an HTML-passthrough exception when nothing else does — `spec.md` calls this out
     explicitly as the one sanctioned exception in an otherwise-tokenized field.
   * Slash menu: drop `mdHide: true` from the `'Underline'` entry in `buildSlashItems()`.
   * Toolbar: `wysiwyg.blade.php` currently only pushes the Underline/Strike buttons
     `@if (! $markdown)`. That condition needs to change so Underline (but not
     necessarily Strike — Strike already round-trips as plain GFM, no custom handler
     needed) renders in markdown mode too. See `ui.md` for the exact Blade diff.

5. **Strikethrough**: no new extension config needed — `Strike` already ships in
   `StarterKit`. Just remove `strike: false` from the `isMarkdown` override (same edit as
   the Underline bullet above, same conditional). No custom `renderMarkdown`/
   `parseMarkdown` needed: `~~text~~` is standard GFM, and `marked` (the parser
   `@tiptap/markdown` uses) handles GFM by default — this is purely "stop suppressing
   a mark that already round-trips," unlike Underline which needs new handlers.

6. **Callout/alert node.** A new custom Tiptap node (not a config tweak to `Blockquote`):
   detects a first line matching `[!NOTE]`/`[!TIP]`/`[!IMPORTANT]`/`[!WARNING]`/
   `[!CAUTION]` inside a blockquote and renders a styled box (border colour + icon per
   type) instead of a plain blockquote. Needs:
   * `parseMarkdown`: recognize the same GitHub callout convention already used in this
     repo's own `documentation/*.md` (per CLAUDE.md) — a blockquote whose first line is
     exactly `[!TYPE]`.
   * `renderMarkdown`: re-emit `> [!TYPE]` + content, so a callout serializes back to the
     exact input a plain-CommonMark reader already renders gracefully as a blockquote
     (this is what makes the construct safe today with zero code changes — don't break
     that fallback while adding editor support for it).
   * No PHP converter change needed for the Markdown path — bare `CommonMarkConverter`
     already renders `> [!TYPE]` as an ordinary blockquote, which is the intended
     degrade-gracefully behavior for any reader without callout-aware rendering (Story
     overview via `Scene::renderedContents`, EPUB export, share page).
   * For the 8 `RichTextFields` HTML fields: presentation over the existing
     `<blockquote>` element, no new tag — a `data-callout-type` attribute (or similar) is
     the only allow-list widening, and only if callouts should work there too (confirm in
     `open-questions.md`).

## `app/Support/RichTextFields.php` changes

`ALLOWED_TAGS` widens. This list is the single source of truth consumed by:
* `HtmlSanitizer` (via `purifierAllowedHtml()`) — the real server-side gate.
* `Import\ContentSanitizer::assertHtmlAllowed()` — **no code change needed there**, it
  already derives its check from the sanitizer, so widening the allow-list automatically
  widens what a `.zip` import accepts. Worth a regression test confirming this (see
  `testing.md`) rather than assuming it.
* The docblock's stated "MUST stay a superset of what the editor's slash menu can
  produce" invariant — re-verify by hand for each new tag added below, same as the
  existing StarterKit alignment.

Proposed additions (confirm exact `renderHTML` output per extension at implementation
time — this list is best-effort from the extensions' documented defaults, not verified
against installed source the way the two nodes in `spec.md`'s "pivotal unknown" were):

```php
public const ALLOWED_TAGS = [
    'p', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's',
    'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'br', 'hr',
    'table', 'thead', 'tbody', 'tr', 'th', 'td', // tables
    'img', // images
    // task list: TaskItem/TaskList render as <ul data-type="taskList"><li data-checked>
    // — confirm whether HTMLPurifier's tag-only allow-list needs an attribute allowance
    // (it currently allows no attributes except a[href]); if TaskItem's checked state
    // needs data-checked, ALLOWED_TAGS's shape (tag-only) may need to grow an
    // attribute-per-tag concept it doesn't have today — flag in open-questions.md.
];
```

`img` needs a matching `ALLOWED_SCHEMES`-style attribute story: HTMLPurifier's
`HTML.Allowed` directive as currently built (`purifierAllowedHtml()`) only special-cases
`a[href]`; `img` needs `src`/`alt`/`title` at minimum, `width`/`height` if resize support
ships. `purifierAllowedHtml()` needs a second special case alongside the `a` one, not a
new mechanism — same shape, more entries.

## `app/Rules/ValidMarkdown.php` change

Switch `CommonMarkConverter` to `GithubFlavoredMarkdownConverter` (or add
`StrikethroughExtension`/`TaskListExtension` to the existing `CommonMarkConverter`
environment — either achieves the same result; prefer the GFM converter since
`Scene::renderedContents()` already uses it via `Str::markdown()`, keeping validation and
rendering on the same grammar rather than validation being a stricter subset). This is
the fix for the strikethrough gap `spec.md` documents: today `~~word~~` validates (tildes
are inert to bare CommonMark) without meaning what the writer expects downstream.

## `app/Models/Scene.php` — no change needed

`renderedContents()` already calls `Str::markdown()`, which Laravel's own
`Illuminate\Support\Str::markdown()` implementation (confirmed by reading
`vendor/laravel/framework/.../Str.php`) builds from `GithubFlavoredMarkdownConverter` —
GFM, strikethrough and task lists included, by default. Nothing to change here; this
surface was already correct, per `spec.md`'s own finding.

## `app/Services/EpubExporter.php` change

Its private `converter()` method builds an isolated `CommonMarkConverter` with only
`SmartPunctExtension` added — deliberately isolated from `Scene::renderedContents()` per
its own docblock, so this file needs its **own** addition of
`StrikethroughExtension` (and `TaskListExtension` if task lists should render correctly
in exported EPUBs, which they should — an EPUB with a checkbox rendered as literal
`[ ] item` text would be a regression relative to the editor). Keep the isolation
rationale intact: this is "add the same extensions `Scene::renderedContents` gets, to
this separately-instantiated converter," not "make EPUB share the converter instance."

## Where NOT to add logic

Per CLAUDE.md's "where logic lives" convention, none of this is controller or
model-lifecycle logic — it's all either client-side editor configuration or read-only
reference data (`RichTextFields`) plus validation-rule/render-converter configuration.
No new Service or Action class is needed; nothing here is a multi-step domain workflow.
