# Rich text (WYSIWYG)

Most free-text fields are **rich HTML**: authored in a WYSIWYG editor, stored as a small
allow-listed subset of HTML, rendered back with formatting.

The driving constraint is *"avoid security issues."* Turning a `<textarea>` into an HTML field
makes **stored XSS** the primary risk, so the whole design serves one rule: **the DB never
holds unsafe HTML, because everything is sanitized on write.**

## Field taxonomy — one source of truth

`App\Support\RichTextFields` is the single source of truth for which model+field pairs are rich
HTML (mirroring `PlotlineColors` / `CodexMediaRules` — reference data lives in `app/Support`,
never as magic-string lists scattered across views, requests and models).

| Model | Rich-HTML field(s) |
| --- | --- |
| `Project` | `description` |
| `Act` | `description` |
| `Chapter` | `description` |
| `Plotline` | `description` |
| `Event` | `description` |
| `Scene` | `description`, `notes` |
| `CodexEntry` | `description` |

Everything else is one of:

- **Markdown** — `Scene.contents` only (see the carve-out below).
- **Plain text, untouched** — names/titles, aliases, tags, attribute values. These stay escaped
  (`{{ }}`) and never go near the sanitizer or the editor.

`RichTextFields` also owns the sanitizer **allow-list** (`ALLOWED_TAGS`, `ALLOWED_SCHEMES`),
the helpers rendering it into HTMLPurifier directives, and the predicates (`isRich()`,
`forModel()`, `all()`). **Add a rich field here** — nothing else hardcodes the list.

## Security model

The editor's client-side output is **untrusted input**. A WYSIWYG that "sanitizes in the
browser" is a UX nicety, not a security control: the payload can be crafted to bypass the
editor entirely (a direct POST, a tampered form field). Safety comes entirely from the server.

### 1. Sanitize on write (the real gate)

- `App\Services\HtmlSanitizer` wraps [HTMLPurifier](http://htmlpurifier.org/), configured from
  the `RichTextFields` allow-list (`HTML.Allowed`, `URI.AllowedSchemes`, plus
  `AutoFormat.RemoveEmpty` to drop empty elements the editor leaves behind). One method:
  `clean(string $html): string`.
- It runs through **per-field set-mutators**, wired by the
  `App\Models\Concerns\SanitizesRichHtml` trait. The shared `description` mutator lives in the
  trait; a model with an extra rich field adds its own delegating to `cleanRichHtml()` (see
  `Scene::notes()`). Null/empty is preserved, so a nullable column keeps storing `null`, not
  `""`.
- **A set-mutator, not a `booted()` hook** — the deliberate choke point. Mutators still run
  under `WithoutModelEvents`; model events don't. The DB can't hold unsafe HTML regardless of
  write path: controller, seeder or tinker.
- `App\Rules\SanitizeHtml` is a Form Request rule on every rich field (mirroring
  `ValidMarkdown` for Markdown fields). It only asserts the value is *processable* HTML — the
  cleaning is the mutator's job.

### 2. The allow-list

`RichTextFields::ALLOWED_TAGS` — everything outside it is stripped:

```
p, h1, h2, h3, h4, strong, em, u, s,
ul, ol, li, blockquote, code, pre, a, br, hr,
table, thead, tbody, tr, th, td,
img,
label, input, span, div
```

Rows 3–5 came from `expand-tip-tap`: the table extension; `img` for image *references*
(uploading is still out of scope); and `label`/`input`/`span`/`div` for the checkbox markup
TipTap's `TaskItem`/`TaskList` render.

`RichTextFields::ALLOWED_ATTRIBUTES` is a separate `tag => list<attribute>` map — kept apart
from `ALLOWED_TAGS` on purpose — listing what survives per tag:

- `a[href]` — restricted to the `http`/`https` schemes in `ALLOWED_SCHEMES`. Relative URLs
  carry no scheme and remain allowed; `javascript:` and `data:` are blocked by omission.
- `img[src|alt|title|width|height]`
- `ul[data-type]`, `li[data-type|data-checked]`, `input[type|checked|disabled]`
- `td[colspan|rowspan]` / `th[colspan|rowspan]` — table merge/split, structural not
  presentational.
- `blockquote[data-callout-type]` — the callout node.

A tag absent from the map is allowed bare, with no attributes. Deliberately still **no**
`<script>` / `<iframe>` / `<object>`, no `style`/`class`, no event handlers anywhere.

### 3. Render only sanitized content, only via `x-rich-text`

Because content is cleaned on write, rendering it with `{!! !!}` is "intentionally rendering
trusted HTML". Two components:

- **`x-rich-text`** — the **only** place rich user HTML is echoed with `{!! !!}`. Detail/show
  pages. Its `prose` classes mirror the Story overview's Markdown rendering so rich HTML reads
  consistently.
- **`x-rich-text-excerpt`** — a short **plain-text** preview for index/table cells: `stripTags`
  + `squish` + `limit`, rendered *escaped* (`{{ }}`). No markup leaks into a striped `x-table`
  row.

> [!WARNING]
> **Never trust the client, and never `{!! !!}` a rich field anywhere but `x-rich-text`.** The
> stored HTML is safe *only* because it was sanitized on write. Don't add a second `{!! !!}` on
> user content, don't "sanitize in the browser and skip the server", and don't ship a rich
> field before its write-path sanitization exists — that combination is a direct stored-XSS
> hole. If the sanitizer isn't wired for a field, keep it escaped or Markdown until it is.

## The `Scene.contents` Markdown carve-out

`Scene.contents` — the manuscript prose — is **not** a rich-HTML field:

- Stored as clean CommonMark, validated by `ValidMarkdown`, rendered via `Str::markdown()`.
- Deliberately absent from `RichTextFields`, no set-mutator (see the comment on
  `Scene::notes()`), never touches `HtmlSanitizer`.
- What changed is only the **editing UI**: it uses `x-wysiwyg` in **`markdown` mode**,
  hydrating from and serializing back to Markdown. The storage contract is unchanged.

**Why Markdown at all:** manuscript content is long-form prose that authors paste, diff and
export, so a plain-text source is the right storage format (portable, greppable,
merge-friendly). The shorter *descriptions* and *notes* store HTML. The two Scene text fields
intentionally differ in both storage format and editor mode.

## The editor

`x-wysiwyg` (`resources/views/components/wysiwyg.blade.php`) is the single reuse point
replacing a `<textarea>`. Props: `name`, `id`, `value`, `rows`, `minHeight`, `placeholder`,
`disabled`, and **`markdown`**.

- **Progressive enhancement.** The component renders a real `<textarea>` holding the value, so
  a JS-off submit still works and `old()` repopulates on validation failure. Alpine
  (`resources/js/wysiwyg.js`, registered in `app.js`) mounts the editor over it, hydrates from
  it, and syncs edits back on every change and again on submit. Pre-mount state is hidden with
  `style="display:none"` (no `x-cloak`), matching the other interactive components.
- **Library: Tiptap** — `@tiptap/core` + `@tiptap/starter-kit` v3, plus `@tiptap/suggestion`
  (slash menu) and `@tiptap/markdown`. One editor framework only; the integration is fully
  encapsulated behind the component and `wysiwyg.js`, so swapping libraries never touches a
  view or controller.

**Two modes:**

| Mode | Value | Serializes with | Used by |
|---|---|---|---|
| HTML (default) | sanitized HTML | `getHTML()` | every rich-HTML field above |
| Markdown (`markdown` prop) | CommonMark/GFM | `getMarkdown()` | `Scene.contents` only |

In Markdown mode, Underline and Strikethrough are **both** enabled: Strikethrough round-trips
as plain GFM (`ValidMarkdown` parses GFM, not plain CommonMark), and Underline serializes to a
literal `<u>…</u>` passthrough that GFM's raw-inline-HTML tolerance carries through (see
`MarkdownUnderline`). Table merge/split and image resize stay **HTML-mode only** — both are
lossy in Markdown, hence the fallback warnings below.

**Toolbar + slash menu.** Two ways to format, producing the same commands: an always-visible
toolbar, and a Notion-style `/` menu (headings H1–H4, bold/italic/underline/strike/sub/super,
bullet/ordered/task lists, blockquote, callout, inline code and code block, link, horizontal
rule, table, image). The menu reuses `@tiptap/suggestion` and its bundled `@floating-ui/dom` —
**no extra dependency** — and every item invokes the same command the toolbar calls, so it adds
no new node/mark surface.

The toolbar's buttons are **data**, in `App\Support\WysiwygToolbar` (one array per cluster;
merge/split-cell gated on the field's format there, not in the template). Every button renders
through `x-wysiwyg.toolbar-button`, which owns the one `<button>` shape and the base classes,
and takes either a `command` (+`args`) or a raw JS `action` for the bespoke helpers
(`setLink()`, `setImage()`, `setCalloutType()`). The six collapsed clusters (Style,
Typography, Lists, Callout, Code, Table) all render through one `x-wysiwyg.toolbar-dropdown`
component, fed the matching `WysiwygToolbar` array.

> [!WARNING]
> Don't give that component a `class=""` of your own alongside its `$attributes->merge()` —
> two `class` attributes on one element are not merged by the browser, the second is dropped.
> That silently cost the table-structure buttons their sizing until it was fixed.

**Tables, images, task lists and callouts** (`expand-tip-tap`) round-trip in both modes.

- Adding/removing a table row or column works in both modes: it always keeps the grid
  rectangular, unlike merge/split.
- A **callout** is a `> [!NOTE]` / `[!TIP]` / `[!IMPORTANT]` / `[!WARNING]` / `[!CAUTION]`
  blockquote — GitHub's alert convention, recognized on a blockquote's first line in both
  formats. Implemented as a custom `Callout` node presenting over `<blockquote>` via
  `data-callout-type` in HTML mode, re-serializing as `> [!TYPE]` + `> `-prefixed body in
  Markdown. The toolbar's Callout dropdown lists the five types by name: picking one inserts a
  callout, or changes the type of the one the cursor is already in (`setCalloutType()`).

> [!IMPORTANT]
> **Editor output must stay ⊆ the allow-list.** Whatever the toolbar *or slash menu* can
> produce must survive `HtmlSanitizer` unchanged, or formatting a user applies is silently
> stripped on save. StarterKit v3 is configured to match `ALLOWED_TAGS` exactly: headings
> capped at 1–4, links restricted to `http`/`https`, and the link prompt rejects other schemes.
> The server sanitizer is the real gate; this is belt-and-braces. Extend the allow-list and you
> extend the StarterKit config **and** the slash item list, and vice versa.

> [!CAUTION]
> **Never store the Tiptap `Editor` in Alpine reactive state.** Alpine wraps reactive
> properties in a `@vue/reactivity` Proxy, and ProseMirror's view/state don't survive being
> proxied — commands run through the proxied instance silently no-op (this once made the
> toolbar dead while the slash menu, using the raw editor, still worked). `wysiwyg.js` keeps
> the editor in a **non-reactive closure variable**; only `ready`/`tick` are reactive. Same
> rule for any stateful third-party instance. Full incident:
> `.specs/shipped/2026-07/wysiwig/resolution-log.md`.

> [!NOTE]
> **Image upload (a new file) is still deferred.** The Image button only inserts a reference to
> an existing URL — no upload endpoint, no `project_media` table. Because nothing can be
> uploaded, unauthenticated-upload authorization and orphaned-image GC remain **not
> applicable**. Real upload would need an `auth`-protected,
> `authorize('update', $project)`-guarded route plus file validation (reuse
> `CodexMediaService` / `CodexMediaRules`); the `img` attributes are already allow-listed.

## Fallback-warning structural checks

Three constructs are lossless in HTML mode but **lossy in Markdown mode**:

1. A **merged table cell** — GFM has no `colspan`/`rowspan`.
2. A **resized image** — GFM has no width/height syntax.
3. An **unmatched raw-HTML wrapper tag** pasted or imported in — CommonMark's raw-HTML
   passthrough unwraps a tag with no registered ProseMirror node and keeps only its content.

None are prevented; they're **detected**, so a caller can warn the user before the loss.

`resources/js/wysiwyg/fallbackChecks.js` is standalone (it doesn't import `wysiwyg.js`, so
depending on it doesn't pull in toolbar/Alpine code), exporting `hasMergedTableCell(doc)`,
`hasResizedImage(doc)`, `hasUnmatchedHtmlWrapperTag(source, editor)` and the combined
`findFallbackWarnings({ editor, source })`. Autosave is its first real consumer, surfacing them
as a save-time warning. `fallbackChecks.test.js` holds the exhaustive cases, including
regression guards so a plain table/image/task-list, an underline mark, or a callout never
false-positives.

## Plain text from rich HTML

`RichText::toPlainText()` reduces stored HTML to text for consumers that must not see markup —
search snippets today, [word counting](word-count.md) since. It breaks on the closing tag of
**every block element** (`p`, `h1`–`h6`, `ul`/`ol`/`li`, `blockquote`, `pre`, `div`, and the
table tags), plus `<br>`, then strips what remains.

> [!WARNING]
> The break is load-bearing, not cosmetic. Breaking only on `</p>` let `strip_tags()` glue the
> last word of one block to the first of the next — `<h1>Chapter One</h1><p>She waited.</p>`
> became `Chapter OneShe waited.`, which mangles search snippets and makes any word count
> short by one per boundary. Inline elements (`strong`, `em`, `code`, `a`, `span`, …) are
> excluded on purpose: they sit *inside* a sentence, and breaking on them would split a word's
> own line.

`h5`/`h6` are in the list although the sanitizer stops at `h4` — `Str::markdown()`, the
`Scene.contents` path, emits all six.

## Where things live

| Concern | Location |
| --- | --- |
| Field taxonomy + allow-list (source of truth) | `app/Support/RichTextFields.php` |
| Rich HTML → plain text | `app/Support/RichText.php` (`toPlainText()`) |
| Sanitization service (HTMLPurifier) | `app/Services/HtmlSanitizer.php` |
| Write-path mutators | `app/Models/Concerns/SanitizesRichHtml.php` (+ per-model extra mutators) |
| Form Request rule | `app/Rules/SanitizeHtml.php` |
| Safe rendering | `resources/views/components/rich-text.blade.php`, `rich-text-excerpt.blade.php` |
| Editor component | `resources/views/components/wysiwyg.blade.php` |
| Editor integration (Tiptap/Alpine) | `resources/js/wysiwyg.js` (registered in `resources/js/app.js`), `resources/css/app.css` |
