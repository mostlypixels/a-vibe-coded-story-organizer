# Rich text (WYSIWYG)

Most of the app's free-text fields are **rich HTML**: authored in a WYSIWYG editor, stored as a
small allow-listed subset of HTML, and rendered back with formatting. This page explains the
field taxonomy, the security model that makes rendering user HTML safe, the one Markdown-only
carve-out, and the editor.

The driving constraint of the feature is *"avoid security issues."* Turning a `<textarea>` into
an HTML field introduces **stored XSS** as the primary risk, so the whole design is organised
around a single rule: the DB never holds unsafe HTML, because everything is sanitized on write.

## Field taxonomy — one source of truth

`App\Support\RichTextFields` is the single source of truth for which model + field pairs are
rich HTML (mirroring `PlotlineColors` / `CodexMediaRules` — reference data lives in
`app/Support`, never as magic-string lists scattered across views, requests, and models).

The rich-HTML fields:

| Model | Field(s) |
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
- **Plain text, untouched** — names/titles, aliases, tags, attribute values, etc. These stay
  escaped (`{{ }}`) and never go near the sanitizer or the editor.

`RichTextFields` also owns the sanitizer **allow-list** (`ALLOWED_TAGS`, `ALLOWED_SCHEMES`) and
the helpers that render it into HTMLPurifier directives, plus small predicates
(`RichTextFields::isRich($model, $field)`, `::forModel()`, `::all()`). If you add a rich field,
add it here — nothing else hardcodes the list.

## Security model

The editor's client-side output is **untrusted input**. A WYSIWYG editor that "sanitizes in the
browser" is a UX nicety, not a security control: the payload can be crafted to bypass the editor
entirely (a direct POST or a tampered form field). Safety comes entirely from the server.

### 1. Sanitize on write (the real gate)

`App\Services\HtmlSanitizer` wraps [HTMLPurifier](http://htmlpurifier.org/) — the well-audited
standard — configured from the `RichTextFields` allow-list (`HTML.Allowed`,
`URI.AllowedSchemes`, plus `AutoFormat.RemoveEmpty` to drop empty elements the editor leaves
behind). It exposes a single `clean(string $html): string`.

Sanitization runs through **per-field set-mutators**, wired via the
`App\Models\Concerns\SanitizesRichHtml` trait. Every model with a rich field uses the trait; the
shared `description` mutator lives in the trait, and a model with an extra rich field adds its
own mutator delegating to `cleanRichHtml()` (see `Scene::notes()`). Null/empty is preserved
as-is so a nullable column keeps storing `null` rather than `""`.

A **set-mutator, not a `booted()` hook**, is the deliberate choke point: mutators still run under
`WithoutModelEvents`, whereas model events do not. So the DB can never hold unsafe HTML
regardless of the write path — controller, seeder, or tinker.

`App\Rules\SanitizeHtml` is a Form Request rule attached to every rich field (mirroring how
`ValidMarkdown` guards the Markdown fields). It only asserts the value is *processable* HTML; the
actual cleaning is the mutator's job on the model write path.

### 2. The allow-list

`RichTextFields::ALLOWED_TAGS` — everything outside it is stripped:

```
p, h1, h2, h3, h4, strong, em, u, s, ul, ol, li, blockquote, code, pre, a, br, hr
```

Only `<a>` carries an attribute (`href`), restricted to the `http` / `https` schemes in
`ALLOWED_SCHEMES` (relative URLs carry no scheme and remain allowed; `javascript:` and `data:`
are blocked by omission). Deliberately **no** `<script>` / `<iframe>` / `<object>`, no `<img>`
(image upload is a v2 concern — see below), no `style`/`class`, and no event-handler attributes.

### 3. Render only sanitized content, only via `x-rich-text`

Because content is cleaned on write, rendering it back with `{!! !!}` is "intentionally rendering
trusted HTML." Two display components exist:

- **`x-rich-text`** (`resources/views/components/rich-text.blade.php`) — the **only** place rich
  user HTML is echoed with `{!! !!}`. Used on detail/show pages. Its `prose` classes mirror the
  Story overview's Markdown rendering so rich HTML reads consistently across the app.
- **`x-rich-text-excerpt`** — a short, **plain-text** preview for index/list table cells:
  `stripTags` + `squish` + `limit`, rendered *escaped* (`{{ }}`). No markup ever leaks into a
  striped `x-table` row, and the layout stays intact. Full rich rendering happens only on detail
  pages.

> [!WARNING]
> **Never trust the client, and never `{!! !!}` a rich field anywhere but `x-rich-text`.** The
> stored HTML is safe *only* because it was sanitized on write. Do not add a second `{!! !!}` on
> user content, do not "sanitize in the browser and skip the server," and do not ship a rich
> field before its write-path sanitization exists — a rich field rendered with `{!! !!}` and no
> server-side sanitization is a direct stored-XSS hole. If the sanitizer isn't wired for a field,
> keep it escaped (`{{ }}`) or Markdown until it is.

## The `Scene.contents` Markdown carve-out

`Scene.contents` — the actual manuscript prose — is **not** a rich-HTML field. Its stored value
stays **clean CommonMark**: validated with the `ValidMarkdown` rule and rendered via
`Illuminate\Support\Str::markdown()` on the Story overview, exactly as before this feature. It is
deliberately absent from `RichTextFields`, has no set-mutator (see the comment on
`Scene::notes()`), and never touches `HtmlSanitizer`.

What *did* change is only the **editing UI**: `contents` now uses the same `x-wysiwyg` editor as
the other fields, but in **`markdown` mode** — the editor hydrates from the stored Markdown and
serializes back to Markdown on save (see "The editor" below). The storage contract is unchanged;
the field just gained a WYSIWYG authoring experience over a Markdown value.

Why Markdown at all: manuscript content is long-form prose that authors often paste, diff, and
export; a plain-text Markdown source is the right storage format for it (portable, greppable,
merge-friendly), whereas the shorter *descriptions* and *notes* store HTML. `Scene.notes` (a
short annotation) is **rich HTML**; `Scene.contents` (the prose) is **Markdown** — the two Scene
text fields intentionally differ in both storage format and editor mode.

## The editor

`x-wysiwyg` (`resources/views/components/wysiwyg.blade.php`) is the single reuse point that
replaces a `<textarea>` on the forms. Props: `name`, `id`, `value`, `rows`, `minHeight`,
`placeholder`, `disabled`, and **`markdown`** (a boolean that switches the field from HTML mode
to Markdown mode — see below).

**Progressive enhancement.** The component renders a real `<textarea>` holding the value, so a
JS-off submit still works and `old()` repopulates on validation failure. Alpine (see
`resources/js/wysiwyg.js`, registered in `resources/js/app.js`) mounts the editor over the
textarea, hydrates from it, and syncs edits back into it on every change and again on submit.
Pre-mount state is hidden with `style="display:none"` (no `x-cloak`), matching the other
interactive components. Placeholder + slash-menu styling live in `resources/css/app.css`.

**Library: Tiptap** (`@tiptap/core` + `@tiptap/starter-kit` v3, plus `@tiptap/suggestion` for the
slash menu and `@tiptap/markdown` for the Markdown field). One editor framework only (anti-bloat)
— the integration is fully encapsulated behind `x-wysiwyg` and `resources/js/wysiwyg.js`, so every
view talks to the editor only through the Blade component and swapping libraries never touches a
view or controller.

**Two modes.**

- **HTML mode (default).** The value is sanitized HTML; the editor serializes with `getHTML()`.
  Used by every rich-HTML field in the taxonomy above.
- **Markdown mode (`markdown` prop).** Used only by `Scene.contents`. The `@tiptap/markdown`
  extension hydrates the editor from the stored Markdown (`contentType: 'markdown'`) and
  serializes back with `editor.getMarkdown()` on write. Underline and Strike are **disabled** in
  this mode (neither round-trips to clean CommonMark), so the toolbar and slash menu both hide
  them. The stored value stays Markdown; `ValidMarkdown` + `Str::markdown()` remain the gate.

**Toolbar + slash menu.** Two ways to format, both producing the same commands: an always-visible
toolbar, and a Notion-style `/` slash command menu (headings H1–H4, bold/italic + underline/strike
in HTML mode, bullet/ordered lists, blockquote, inline code and code block, link, horizontal
rule). The slash menu reuses `@tiptap/suggestion` (already a dependency) for the trigger and its
bundled `@floating-ui/dom` for popup positioning — **no extra dependency**. Because every slash
item invokes the same StarterKit command the toolbar calls, the menu adds no new node/mark
surface.

> [!IMPORTANT]
> **Editor output must stay ⊆ the allow-list.** Whatever the toolbar *or slash menu* can produce
> must survive `HtmlSanitizer` unchanged — otherwise formatting a user applies is silently
> stripped on save. StarterKit v3 is configured to match `RichTextFields::ALLOWED_TAGS` exactly:
> headings capped at levels 1–4, links restricted to `http`/`https`, and the link prompt rejects
> any other scheme. The server sanitizer is the real gate; this client-side alignment is
> belt-and-braces. If you extend the allow-list, extend the StarterKit config **and** the slash
> item list to match, and vice versa.

> [!CAUTION]
> **Never store the Tiptap `Editor` in Alpine reactive state.** Alpine wraps reactive properties
> in a `@vue/reactivity` Proxy, and ProseMirror's view/state do not survive being proxied —
> commands run through the proxied instance silently no-op (this once made the toolbar dead while
> the slash menu, which uses the raw editor, still worked). `wysiwyg.js` keeps the editor in a
> **non-reactive closure variable**; only `ready`/`tick` are reactive. Same rule for any stateful
> third-party instance. See `.specs/shipped/wysiwig/resolution-log.md` for the full incident.

> [!NOTE]
> **Image upload is deferred to v2.** There is no upload endpoint and no `project_media` table;
> `<img>` is not in the allow-list, and the toolbar has no image insert. Because nothing can be
> uploaded, the related concerns — unauthenticated-upload authorization and orphaned-image
> garbage collection — are **not applicable** in v1. Adding image upload later would introduce an
> `auth`-protected, `authorize('update', $project)`-guarded route plus file validation (reuse
> `CodexMediaService` / `CodexMediaRules`), and only then does `<img[src|alt]>` join the
> allow-list.

## Where things live

| Concern | Location |
| --- | --- |
| Field taxonomy + allow-list (source of truth) | `app/Support/RichTextFields.php` |
| Sanitization service (HTMLPurifier) | `app/Services/HtmlSanitizer.php` |
| Write-path mutators | `app/Models/Concerns/SanitizesRichHtml.php` (+ per-model extra mutators) |
| Form Request rule | `app/Rules/SanitizeHtml.php` |
| Safe rendering | `resources/views/components/rich-text.blade.php`, `rich-text-excerpt.blade.php` |
| Editor component | `resources/views/components/wysiwyg.blade.php` |
| Editor integration (Tiptap/Alpine) | `resources/js/wysiwyg.js` (registered in `resources/js/app.js`), `resources/css/app.css` |
