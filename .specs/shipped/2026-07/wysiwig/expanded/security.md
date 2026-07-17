# WYSIWYG textareas — Security

This is the load-bearing document for the feature. The spec's driving constraint is "avoid
security issues," and turning textareas into HTML fields introduces **stored XSS** as the
primary risk, plus **unauthenticated upload** as the secondary one.

## Threat 1 — Stored XSS (the big one)

Rich HTML fields mean we persist user-authored HTML and later render it with `{!! !!}`. If the
stored HTML contains `<script>`, `onerror=`, `javascript:` URLs, `<iframe>`, etc., it executes
in the browser of anyone (including the owner) who views the page.

**Rule: the editor's client-side output is untrusted input.** A WYSIWYG editor sanitizing in
the browser is a UX nicety, not a security control — the payload can be crafted to bypass the
editor entirely (direct POST, tampered form field).

### Mitigation (required)

1. **Server-side sanitization on write.** Run every rich-HTML field through an HTMLPurifier-
   backed `App\Services\HtmlSanitizer` before persistence (set-mutator or Form Request pass —
   see `architecture.md`). HTMLPurifier is the well-audited standard; it enforces a strict
   allow-list of tags/attributes/protocols and is safer than a hand-rolled regex or
   `strip_tags`.
2. **Strict allow-list**, defined once in `app/Support`. Allow only what the slash commands
   produce: `p, h1–h4, strong, em, u, s, ul, ol, li, blockquote, code, pre, a[href], br, hr`,
   and (if image upload ships) `img[src|alt]`. Disallow everything else — no `style`
   attributes, no `class` from the user, no `<script>`, `<iframe>`, `<object>`, event
   handlers. Restrict `a[href]` / `img[src]` to `http`, `https`, and relative URLs (block
   `javascript:`, `data:` except perhaps whitelisted image data URIs — prefer blocking
   `data:`).
3. **Render only sanitized content**, exclusively through the `x-rich-text` component. Grep the
   codebase to ensure no rich field is echoed with `{!! !!}` outside that component.
4. **Defense in depth:** because content is cleaned on write, `{!! !!}` renders known-safe
   HTML. Consider a Content-Security-Policy header as an extra layer (out of scope but worth a
   note in docs).

> [!WARNING]
> Do **not** ship the HTML fields without server-side sanitization, even temporarily. A rich
> field rendered with `{!! !!}` and no sanitization is a direct stored-XSS hole. If the
> sanitizer isn't ready, the fields must stay escaped (`{{ }}`) / Markdown.

### Test the invariant

A feature test posts a payload containing `<script>alert(1)</script>`,
`<img src=x onerror=alert(1)>`, and `<a href="javascript:alert(1)">` to a rich field and
asserts the **stored** value contains none of them (script stripped, `onerror` removed, href
neutralized). See `testing.md`.

## Threat 2 — Unauthenticated / unauthorized image upload

The spec explicitly requires that image upload not allow non-logged-in users to upload. This
is only relevant **if** image upload ships (galleries are deprioritized; a single inline image
insert may or may not be in scope — see `open-questions.md`).

### Mitigation (required if upload ships)

- Upload route lives inside the `auth` middleware group → anonymous requests are redirected /
  rejected. **Verify** no upload endpoint is registered outside `auth` (Redactix defaults must
  be overridden to point at *our* route).
- The controller calls `$this->authorize('update', $project)` and the Form Request mirrors it
  → a logged-in non-owner gets **403**. Authenticated ≠ authorized.
- **Validate the file**: mime allow-list + max size via `CodexMediaRules` (reuse the Codex
  rules). Reject non-images. Store with a generated name (never the client filename in the
  path) via `CodexMediaService`, so an uploaded `.php` can't be executed and path traversal is
  impossible.
- Serve uploaded files from a location that is **not executable** (public disk / storage
  symlink as Codex media already does), with correct content-type.

### Test the invariant

- Anonymous POST → redirect to login / 401.
- Logged-in non-owner POST → 403.
- Owner POST of a non-image (e.g. `.php`, oversized) → 422.
- Owner POST of a valid image → 201/200 with a JSON `url`, file on disk. See `testing.md`.

## Threat 3 — Library supply-chain / maintenance

"Avoid bloat to avoid security issues" is itself a security requirement.

- Choose **one** editor library and pin it. Prefer a maintained project with a small
  dependency tree. Redactix is the lightweight favorite; **verify current maintenance status
  and that it exposes a custom upload handler** before committing (see `open-questions.md`).
- If Redactix is unmaintained or forces an external/unauthenticated upload endpoint, fall back
  to Tiptap (mature, explicit upload handler) despite the larger bundle.
- Record the decision and the allow-list rationale in `documentation/` per the guidelines.

## Non-negotiables checklist

- [ ] Server-side HTMLPurifier sanitization on every rich-HTML write path.
- [ ] Strict, centralized tag/attribute/protocol allow-list.
- [ ] `{!! !!}` only via the `x-rich-text` component, on already-sanitized data.
- [ ] Upload endpoint (if any) behind `auth` **and** `authorize('update', $project)`.
- [ ] Upload file validation (mime + size) + generated storage name.
- [ ] Feature tests for the XSS-stripping invariant and the upload authorization negatives.
