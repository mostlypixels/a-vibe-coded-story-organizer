# WYSIWYG textareas — Architecture

Follow the guidelines: thin controllers, validation in Form Requests, reusable rules in
`app/Rules`, reference/config data in `app/Support` or `app/Enums`, non-trivial reusable
workflows in `app/Services`. This feature adds no new domain aggregate — it's a cross-cutting
input/rendering concern layered over existing controllers.

## 1. Single source of truth for "which fields are rich HTML"

Do **not** scatter `rich HTML vs markdown vs plain` knowledge across views and requests. Add
one reference object under `app/Support` (mirrors `PlotlineColors` / `CodexMediaRules`):

```php
// app/Support/RichTextFields.php  (name TBD)
// Maps model+field → editor mode, and exposes the HTMLPurifier allow-list config.
```

Everything derives from it: which `x-wysiwyg` a form renders, which Form Request rules apply
`SanitizeHtml`, and (optionally) a Blade helper for "is this field rich?". This is the
DRY/anti-magic-string guideline applied — no hardcoded field lists in multiple files.

## 2. Sanitization: the `SanitizeHtml` layer

The crux of the security story (details in `security.md`). Structure it like `ValidMarkdown`:

- **`App\Rules\SanitizeHtml`** — a reusable validation rule attached to every rich-HTML field
  in the Form Requests, mirroring how `new ValidMarkdown` is attached to `contents`/`notes`.
  Its job is to *reject or normalize* structurally; the actual cleaning happens in a service.
- **`App\Services\HtmlSanitizer`** (the app's next `app/Services` class, alongside
  `AttributeTimeline` / `CodexMediaService`) — wraps HTMLPurifier with the allow-list from
  `RichTextFields`. Single method `clean(string $html): string`. One place to configure
  allowed tags/attributes/protocols.
- **Where cleaning runs on the way in:** prefer a Form Request `passedValidation()` /
  `prepareForValidation()` hook, or a **model cast/mutator** so the stored value is always
  clean regardless of entry path (controller, seeder, tinker). A cast is the most robust
  (single choke point, survives `WithoutModelEvents`), but casts run on every hydrate — a
  set-mutator (`Attribute::make(set: ...)`) that cleans on write only is a good middle ground.
  Recommend a **set-mutator per rich field** delegating to `HtmlSanitizer`, so the DB never
  holds unsafe HTML. Decide in `open-questions.md`.

Do **not** rely on the JS editor to sanitize — client output is untrusted input.

## 3. Rendering: the `x-rich-text` display component

- **`resources/views/components/rich-text.blade.php`** — receives an HTML string and emits it
  with `{!! !!}` wrapped in a `prose` container (Tailwind Typography classes already used on
  the Story overview: `class="prose prose-sm max-w-none ..."`). Because the value was
  sanitized on save, this is "intentionally rendering trusted HTML" per the guidelines.
- **List/index previews** (`acts/index`, `chapters/index`, etc. currently `{{ $x->description }}`):
  do **not** dump full HTML into a table cell. Use a helper to strip tags to a short text
  excerpt (`Str::of($html)->stripTags()->limit(120)`), keeping the table layout intact. Add an
  `x-rich-text-excerpt` variant or a `@php` helper — flagged for consistency.
- Scene `contents` on the Story overview keeps `Str::markdown()` — unchanged.

## 4. The editor component: `x-wysiwyg`

- **`resources/views/components/wysiwyg.blade.php`** — the single reuse point. Props mirror the
  current textarea call sites: `name`, `id`, `:value`, `rows`, `placeholder`, error slot
  handled by the caller (`x-input-error`).
- **Progressive enhancement:** render a real `<textarea>` (so no-JS submits still work and
  `old()` repopulates), then let Alpine mount the editor over it and sync edits back into the
  textarea before submit. This matches the existing components' Alpine + `style="display:none"`
  convention (no `x-cloak`), and avoids a hidden-input-only design that breaks without JS.
- Initialize the editor in a small Alpine component or an `resources/js` module imported by
  `app.js` (which already boots Alpine). Keep the editor config (slash commands, upload URL)
  in one JS module so it isn't duplicated per field.
- Replace the textareas in: `projects/{create,edit}`, `acts/{create,edit}`,
  `chapters/{create,edit}`, `plotlines/{create,edit}`, `events/{create,edit}`,
  `scenes/{create,edit}` (description + notes), `codex/partials/fields` (description).
  **Leave `scenes` `contents` as a plain/Markdown textarea.**

## 5. Image upload endpoint (only if image upload ships)

Follow the shallow-nesting + authorize-via-Project convention exactly:

- **Route:** `POST /projects/{project}/media` (nested; upload always knows its project),
  name `projects.media.store`, inside the `auth` middleware group like every other route.
- **Controller:** a thin `ProjectMediaController@store` (or reuse a Codex controller if the
  association fits). Action reads `$project` → `$this->authorize('update', $project)` →
  delegates to `CodexMediaService`/`ProjectMediaService` → returns JSON `{ url: ... }` in the
  shape Redactix/Tiptap's upload handler expects.
- **Form Request:** `StoreProjectMediaRequest` with `authorize()` mirroring
  `$this->user()->can('update', $this->route('project'))` (same pattern as
  `StoreSceneRequest`), and `rules()` validating the file via `CodexMediaRules`
  (`imageAccept()`, max size). **Never** rely on route binding alone.
- **No new policy** — authorize through `ProjectPolicy` like all child resources.

> [!WARNING]
> This endpoint is the specific security concern the spec calls out. The `auth` middleware
> blocks anonymous uploads; the `authorize('update', $project)` blocks non-owners. Both are
> required — middleware alone would let any logged-in user upload into any project. Cover the
> non-owner `403` in a feature test.

## 6. Frontend build

- Add the chosen editor as an npm dependency; import and configure it in a
  `resources/js/wysiwyg.js` module, imported from `resources/js/app.js`. Bundled by the
  existing Vite pipeline (`npm run build`) — no config change beyond the import.
- Keep it to **one** editor library (anti-bloat goal). Do not add a second editor for the
  Markdown field.
- CSRF: the upload endpoint needs the token; `resources/js/bootstrap.js` already sets
  `X-Requested-With` on axios — send the CSRF token via the standard meta tag/axios header the
  same way, or include `@csrf` handling in the upload fetch.

## What stays unchanged

- Authorization model (walk up to `Project` via `ProjectPolicy`), shallow routing, Form
  Request mirror-authorization, the Story overview's Markdown rendering of `Scene.contents`,
  `ValidMarkdown`, `AttributeTimeline`, `CodexMediaService`.
