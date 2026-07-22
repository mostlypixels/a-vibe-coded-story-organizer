---
status: draft
---

# Expand Tip Tap

Establish what the TipTap editor can and cannot round-trip, widen it where that is
cheap and safe, and decide what happens to content it cannot represent.

## Why now

* `.specs/draft/autosave-with-revisions` is **blocked on this** — see its
  `handoff.md` §11.4. It cannot state what happens when the editor silently drops
  formatting until this spec says what the editor supports.
* Today, destroying unrepresentable content requires a deliberate **Save** click.
  With autosave, two seconds of typing does it — same destruction, no consent.

## The problem

* `resources/js/wysiwyg.js` configures **`StarterKit` only**. `node_modules/@tiptap`
  contains no Table and no Image extension.
* `ValidMarkdown` accepts anything CommonMark parses, so `Scene.contents` **can**
  legitimately hold a Markdown table, image or footnote.
* Where does such content come from, if the editor cannot produce it?
  * a project imported from an export `.zip`
  * text pasted from another writing tool
  * scenes written before the TipTap editor shipped
* When the editor hydrates that content it has no node to put it in. **Verified,
  not assumed** — ran a table through `marked.lexer()` directly and traced
  `@tiptap/markdown`'s `MarkdownManager.parseTokens`/`parseFallbackToken`
  (`src/MarkdownManager.ts:445-475`, `:882-926`): a `table` token has no
  `.tokens` array (it carries `header`/`rows`/`align` instead), so the fallback's
  `default` case returns `null`. **A table hydrated today isn't flattened to
  plain lines, it is deleted outright** — headers, rows, all cell text, gone.
  Not every unsupported construct behaves the same way, though: a footnote run
  through the same `marked.lexer()` (`[^1]` / `[^1]: ...`) isn't tokenized
  specially at all without a footnote plugin — it comes back as two ordinary
  `paragraph` tokens, so it survives as visible (if unstyled) literal text
  instead of vanishing. The fallback behaviour needs to be checked per
  construct, not assumed uniform.
* The same gap exists on the rich-HTML side: `RichTextFields::ALLOWED_TAGS` permits
  no `<table>` and no `<img>`, and its docblock states the allow-list **"MUST stay a
  superset of what the editor's slash menu can produce"** — so editor capability and
  sanitizer allow-list are one decision, not two.

## Goals

* Produce an **authoritative, tested list** of what survives a round-trip and what
  does not — for both modes (`markdown` for `Scene.contents`, `html` for the 8
  `RichTextFields` fields).
* Decide, per construct, whether to **support it**, **preserve it untouched**, or
  **warn and flatten it**.
* Give `autosave-with-revisions` a stable contract to build the §11.5 warning on.

## Non-goals

* Autosave behaviour itself — that stays in `autosave-with-revisions`.
* Image *upload* and storage. `RichTextFields` already calls that "a v2 concern",
  and `CodexMediaService` owns media elsewhere. Rendering an existing image
  reference is in scope; an upload pipeline is not.
* Redesigning the editor UI. That is `.specs/draft/editor-interface`.
* Footnotes. No official `@tiptap/extension-footnote` exists — verified via `npm
  view`/registry search, not assumed — so supporting them means a wholly custom
  node (schema, view, `parseMarkdown`/`renderMarkdown`), a materially bigger lift
  than the extension-wiring work the rest of this spec covers. Split out to
  `.specs/draft/footnote-plugin`. This spec still notes footnotes' current
  fallback behaviour above (survives as literal text, doesn't vanish) since that's
  relevant to the fallback-policy decision either spec depends on.

## The pivotal unknown — verified

* `@tiptap/markdown` (3.27.1, built on `marked` ^17) converts **per node**: its
  manager reads `markdownName`, `parseMarkdown`, `renderMarkdown` and `priority`
  off each registered extension. A node only survives a round-trip if its extension
  ships those handlers.
* **Verified against the actual package source** (not docs): pulled the
  `@tiptap/extension-table@3.27.1` and `@tiptap/extension-image@3.27.1` tarballs —
  matching this project's `@tiptap/*` pin — into a scratch directory and read
  `dist/index.js` directly, since neither is installed in this repo yet.
  * **Both ship `parseMarkdown` and `renderMarkdown` directly on the node config.**
    Neither declares `markdownTokenName` or `priority` explicitly — confirmed in
    `@tiptap/markdown`'s `MarkdownManager.registerExtension`
    (`src/MarkdownManager.ts:140-144`) that both default (token name → the
    extension's own `name`; priority → registration order) when omitted, so this
    isn't a gap, it's just unnecessary for these two nodes.
  * **This resolves the fork: it's the "modest" path** — install the two
    extensions, add toolbar/slash-menu entries, widen the sanitizer, test. No
    hand-written Markdown serializers needed.
* **Two round-trip gaps surfaced by reading the source, not assumed** — both need
  their own inventory row, not a blanket "table: safe" / "image: safe":
  * **Table cell merging doesn't round-trip.** The extension ships
    `mergeCells`/`splitCell` commands (colspan/rowspan), but its `parseMarkdown`
    only reconstructs plain `tableRow`/`tableHeader`/`tableCell` from
    `header`/`rows` tokens — no colspan/rowspan handling, because GFM Markdown
    tables have no syntax for a merged cell at all. A merged table survives in
    HTML mode; the merge is silently lost the moment it round-trips through
    `Scene.contents`.
  * **Resized images lose their size in Markdown mode.** The node has
    `width`/`height` attrs (from its `resize` option), but `renderMarkdown` only
    emits `src`/`alt`/`title` — standard `![alt](src "title")` has no syntax for
    dimensions. A resize done in an HTML-mode field is silently dropped on any
    Markdown round-trip.
* **Task lists: decided — support them.** Same "modest" path as tables/images —
  `@tiptap/extension-task-item`/`-task-list` are thin re-export shims; the real
  `TaskItem`/`TaskList` nodes live in `@tiptap/extension-list@3.27.1`, and both
  ship full `parseMarkdown`/`renderMarkdown` (`dist/index.js:1316-1331`,
  `:1490-1493`). Four-part change:
  * install `@tiptap/extension-list`'s `TaskItem`/`TaskList` (note: this is a
    different package than the two shim packages named above — install the one
    with the real implementation)
  * toolbar + slash-menu entry
  * `RichTextFields::ALLOWED_TAGS` widened for whatever tags `TaskItem`/`TaskList`
    render (`ul`/`li` already allowed; likely just a checkbox-state attribute or
    a `data-checked`-style marker to add, not a new tag)
  * round-trip tests
  * PHP side needs no new package — `vendor/league/commonmark/src/Extension/TaskList`
    is already bundled in the `league/commonmark ^2.8` installed here; enabling it
    is the same consistency pass already decided for strikethrough (`ValidMarkdown`,
    `Scene::renderedContents`, `EpubExporter`'s converter all need to agree).

## The rest of the inventory — verified

Ran the remaining constructs through `marked.lexer()` directly and read the actual
`@tiptap/extension-blockquote`, `-hard-break`, `-link`, and `@tiptap/markdown`
source (not assumed from docs). None of these are destructive — three need no
work at all, the other two degrade gracefully:

* **Nested blockquotes — already safe, already installed.** `marked` tokenizes
  them recursively (a `blockquote` token containing a nested `blockquote` token),
  and `@tiptap/extension-blockquote`'s `parseMarkdown` recurses on `token.tokens`
  (`dist/index.js:3064-3067`), so depth falls out for free. `Blockquote` ships as
  one of `@tiptap/starter-kit`'s own dependencies — already active today.
* **Hard line breaks — already safe, already installed.** `marked` gives a
  distinct `br` token for two-trailing-spaces; `@tiptap/extension-hard-break` has
  `markdownTokenName: "br"` plus full `parseMarkdown`/`renderMarkdown`
  (`dist/index.js:5,25-30`). Also bundled inside `starter-kit`.
* **Reference-style links — already safe, already installed, but normalizes.**
  `marked` resolves `[text][1]` + `[1]: url "title"` into an ordinary `link`
  token before `@tiptap/markdown` sees it — indistinguishable from an inline
  link — and `@tiptap/extension-link` (bundled in `starter-kit`) parses it fine.
  `renderMarkdown` always emits inline `[text](url "title")` (`dist/index.js:354-360`),
  never reference form, so this is a **normalization**, the same bucket as the
  `_em_` → `*em*` case already documented below, not new content loss. The
  leftover `def` token (the definition line) correctly contributes nothing.
* **Definition lists — safe by graceful degradation, same bucket as footnotes.**
  `marked` has no built-in support for `Term\n: Definition` syntax at all (Pandoc/MMD
  territory, not CommonMark or GFM) — confirmed it comes back as a literal
  `paragraph` token, so it survives as visible plain text, never destroyed.
* **Raw HTML blocks — more nuanced than a flat safe/unsafe.** `@tiptap/markdown`
  has a deliberate, already-built fallback (`MarkdownManager.ts:939-985`): an
  `isUnrecognizedHtml` check. Genuinely unrecognized markup is preserved as
  **literal visible text**, not dropped. A *recognized* standard tag with no
  matching schema node — e.g. a bare `<div class="letter">…</div>` wrapping a
  `<p>` — gets DOM-parsed via `generateJSON` using the registered extensions'
  `parseHTML` rules: the inner `<p>` survives as a real paragraph, but the `<div>`
  wrapper and its `class` attribute are dropped, since nothing in the schema
  claims that tag. Content survives; structure/attributes on an unmatched
  wrapper tag don't. Needs its own round-trip test to pin this exact case rather
  than rely on this description.

**Synthesis:** with tables/images/task-lists decided and everything above either
already safe or gracefully degrading, there is **no remaining construct in this
spec's inventory that deletes content outright** — the closest things are the
already-documented attribute-level losses (merged table cells, resized image
dimensions, an HTML wrapper tag's attributes), none of which touch the text
itself. This changes the fallback decision below: it's not "stop autosave from
destroying content" (nothing left does), it's "surface these smaller
attribute/structure losses so they're not a silent surprise."

## Rough approach

* **Inventory, final status.** tables (decided), images (decided), strikethrough
  (decided), underline (decided), callouts (decided), task lists (decided),
  nested blockquotes / hard line breaks / reference-style links / definition
  lists / raw HTML blocks (all verified above, no further decision needed) —
  footnotes split out to `.specs/draft/footnote-plugin`
* **Pin it with tests.** A round-trip test is the deliverable that stops this
  regressing on the next TipTap bump. Note there is **no JS test tooling today** —
  `autosave-with-revisions` §9.12 proposes adding **vitest**; this spec is the more
  natural first consumer of it.
* **Tables: decided — support them.** Confirmed modest per the verification
  above (`@tiptap/extension-table` ships real `parseMarkdown`/`renderMarkdown`).
  Four-part change:
  * install `@tiptap/extension-table` (+ `TableRow`/`TableHeader`/`TableCell`)
  * toolbar + slash-menu entry
  * `RichTextFields::ALLOWED_TAGS` widened to include `table`/`tr`/`th`/`td`
    (and `thead`/`tbody` if the extension's `renderHTML` emits them), keeping
    the "superset" invariant true
  * round-trip tests, including the discovered merged-cell gap as an explicit
    case: assert a merged table cell's Markdown-mode loss is
    expected/documented, not an accidental regression
* **Images: decided — support them.** Same verified-modest path as tables —
  `@tiptap/extension-image` ships real `parseMarkdown`/`renderMarkdown`. Four-part
  change:
  * install `@tiptap/extension-image`
  * toolbar + slash-menu entry
  * `RichTextFields::ALLOWED_TAGS` widened to include `img`, keeping the
    "superset" invariant true
  * round-trip tests, including the discovered resize-loses-dimensions gap as an
    explicit case: assert a resized image's dropped `width`/`height` in
    Markdown mode is expected/documented, not an accidental regression
  * Reminder from Non-goals: this covers *rendering* an existing image reference
    only — upload/storage stays out of scope, owned elsewhere by
    `CodexMediaService`.
* **Strikethrough: decided, not blocked on the pivotal unknown above.** Unlike
  tables/images, the `Strike` node already ships in `StarterKit` — it's just turned
  off in Markdown mode (`resources/js/wysiwyg.js`, `strike: false` when
  `isMarkdown`), on the stated grounds that it "doesn't round-trip to clean
  CommonMark." That's stale: `~~text~~` is standard GFM strikethrough, and
  `marked` (`@tiptap/markdown`'s parser) already handles GFM by default. The real
  gap is on the PHP side, where the two hand-built converters don't agree with
  each other or with the editor:
  * `Scene::renderedContents` (`app/Models/Scene.php`) already uses
    `Str::markdown()`, which defaults to `GithubFlavoredMarkdownConverter` — GFM,
    strikethrough included, no change needed.
  * `ValidMarkdown` (`app/Rules/ValidMarkdown.php`) uses a bare `CommonMarkConverter`
    — no GFM. A saved `~~word~~` validates today (tildes are just inert text to
    core CommonMark) but doesn't mean what the writer expects downstream.
  * `EpubExporter`'s own converter (its `converter()` method, deliberately isolated
    from `Scene::renderedContents` — see its docblock) also has no `StrikethroughExtension`,
    so exported EPUBs would render `~~word~~` as literal tildes.
  * **Decision:** enable `Strike` in the editor's Markdown mode, and add
    `League\CommonMark\Extension\Strikethrough\StrikethroughExtension` (or switch
    to `GithubFlavoredMarkdownConverter`, which already includes it) to both
    `ValidMarkdown` and `EpubExporter`'s converter, so all four surfaces — editor,
    validator, shared render path, EPUB export — agree on the same syntax.
* **Underline: decided — keep it, via `<u>` HTML passthrough.** Plain CommonMark
  (and GFM) has no underline syntax at all; there is no token to add. Raised while
  discussing this spec: novel dialogue conventions use underline for emphasis
  distinct from italics (e.g. a character's handwritten letter marking important
  words), so silently dropping it in Markdown mode is a real content loss, not a
  cosmetic gap — worth the one exception.
  * **Decision:** give the `Underline` mark a custom `renderMarkdown`/`parseMarkdown`
    pair that emits/reads literal `<u>text</u>` — core CommonMark already parses raw
    inline HTML, so `ValidMarkdown` needs no change for this specific case.
  * This is the one sanctioned HTML-passthrough exception in an otherwise-tokenized
    Markdown field; worth a one-line comment at the extension config saying why
    `<u>` specifically is allowed through when nothing else is.
  * Consequently `Underline` **stops being `mdHide`**: `resources/js/wysiwyg.js`
    currently both disables the mark outright in Markdown mode
    (`isMarkdown ? { strike: false, underline: false } : {}`) and filters it out of
    the toolbar/slash menu (`mdHide: true`). Both need to go for Markdown-format
    fields (the ones synced to the hidden `<textarea>`, i.e. `Scene.contents`) —
    the mark needs to be enabled there and the toolbar entry needs to stop being
    filtered, or the round-trip support has no way to be invoked.
* **Callout/alert blocks: decided, cheaper than strikethrough.** GitHub's
  `> [!NOTE]` / `[!TIP]` / `[!IMPORTANT]` / `[!WARNING]` / `[!CAUTION]` convention
  (already used in this repo's own `documentation/*.md`, per CLAUDE.md) is plain
  CommonMark — a blockquote with a magic first line, not a new grammar rule. That
  gives it a property nothing else on this list has: it **degrades gracefully with
  zero code changes today**. Any CommonMark renderer — bare `CommonMarkConverter`,
  `marked`, GitHub itself — already parses it as an ordinary blockquote; a reader
  without callout support sees a blockquote with a visible `[!NOTE]` line, not
  flattened or destroyed content.
  * **Decision:** add a TipTap node that recognizes the `[!TYPE]` marker on a
    blockquote's first line and renders it as a styled box (border colour + icon
    per type) instead of a plain blockquote. `renderMarkdown` re-emits
    `> [!TYPE]` + content — no new CommonMark construct, no PHP-converter changes,
    since bare CommonMark's blockquote fallback already handles the untyped case.
  * `RichTextFields::ALLOWED_TAGS` needs no new tag for this — it's presentation
    over the existing `blockquote` element; a `data-callout-type` attribute (or
    similar) is the only widening, and only if callouts should also work in the 8
    HTML `RichTextFields`, not just `Scene.contents`.
* **Decide the fallback policy — reframed by the inventory above.** With nothing
  left in scope that deletes content outright (see Synthesis above), the
  question is no longer "how do we stop autosave from destroying content," it's
  "how do we surface the remaining attribute/structure-level losses" (merged
  table cells, resized image dimensions, an HTML wrapper tag's attributes) so
  they're not a silent surprise. Options, still open, to pick from:
  * flatten silently (today's behaviour) — lowest cost, but the exact gap this
    spec exists to close; not recommended given the reframing above still leaves
    real (if smaller) silent losses
  * flatten, but detect and warn from an explicit config list — no fuzzy diffing,
    so cosmetic `_em_` → `*em*` / reference-link normalisation never triggers a
    false warning; matches the now-smaller, enumerable set of real losses
  * refuse to load such a field into the editor and fall back to a plain
    textarea — disproportionate now that the residual set is this small
* **Document the normalisation.** Even with everything supported, TipTap re-serialises
  Markdown (`_em_` → `*em*`, bullet markers, wrapping), so the first edit of any
  pre-existing scene rewrites the whole document. `autosave-with-revisions` §11.3
  depends on this being stated, not discovered.

## Loose end spotted while writing this

* `RichTextFields`' docblock says `Scene.contents` is *"never routed through the
  sanitizer **or the editor**"* — but `scenes/edit.blade.php` renders it with
  `<x-wysiwyg … markdown>`. The sanitizer half is still true; the editor half is
  stale. Correct it while in here.

## Adjacent but out of scope: SmartPunct on the shared render path

* Raised in the same discussion, but this is a rendering-consistency question, not
  a TipTap round-trip one — no editor change involved. Noted here so it isn't lost,
  not claimed as part of this spec's deliverable.
* `Scene::renderedContents` (`app/Models/Scene.php`) is the single accessor shared
  by the Story overview, the token-based public share page, and the `book/` layer
  of the EPUB export. Today it calls plain `Str::markdown()` — no
  `SmartPunctExtension` (smart dashes, ellipses, curly quotes).
* `EpubExporter` deliberately does **not** use that accessor — its docblock
  documents a grilled decision that scene contents for the EPUB's own content
  documents go through an isolated `CommonMarkConverter` configured with
  `SmartPunctExtension`, specifically so `Scene::renderedContents` stays
  byte-for-byte identical across its three consumers.
* **Decision:** add `SmartPunctExtension` to `Scene::renderedContents` itself
  (not to just the share page in isolation) — this keeps the "one shared render
  path, one behaviour" invariant intact, and means Story overview, the share page,
  and the book export all gain smart punctuation together. Rejected: giving the
  share page its own converter, since that reintroduces the per-surface
  divergence `EpubExporter`'s isolation was grilled specifically to avoid.
