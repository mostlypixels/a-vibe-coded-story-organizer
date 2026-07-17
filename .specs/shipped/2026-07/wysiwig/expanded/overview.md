# WYSIWYG textareas — Overview

## Problem statement

Every long-text field in the app is a bare `<textarea>`. Some are plain text (the various
`description` fields on Project / Act / Chapter / Plotline / Event / Scene), some are
Markdown (Scene `contents` and `notes`, Codex `description`). Authors have no formatting
affordances — bold, headings, lists, links — and the Markdown fields require the author to
know Markdown syntax and only render formatted on the read-only Story overview.

The spec asks for a **Notion-like WYSIWYG editor** on those fields, turning most of them into
rich **HTML** fields, while keeping the Scene `contents` field **Markdown-only**. The primary
constraint is **not to bloat the codebase and not to open security holes** — specifically, the
editor's image-upload feature must never let an unauthenticated (or unauthorized) user upload
files.

## Goals

- Replace plain textareas with a Notion-style WYSIWYG editor on the fields we designate as
  **rich HTML** fields.
- Implement Redactix's slash-command feature set for HTML fields (headings, lists, quotes,
  code, links, etc.). Image **galleries** are explicitly *not* a priority.
- Keep the Scene `contents` field **Markdown-only** (per the spec's hard requirement).
- Store and render user HTML **safely** — server-side sanitization is mandatory, never trust
  the client-side editor's output.
- If the editor supports image upload, gate it behind `auth` **and** project authorization,
  reusing the existing `CodexMediaService` storage/validation patterns.
- Introduce the editor through **one reusable Blade component** (`x-wysiwyg`) so all forms
  share it — matching the project's "reuse before you build" component convention.
- Keep the JS footprint small (the anti-bloat constraint): a single editor library, bundled
  through the existing Vite pipeline, no second framework.

## Non-goals

- Real-time collaborative editing.
- Image galleries / media library UI inside the editor.
- Converting existing stored Markdown/plain text to HTML in bulk with high fidelity (a
  best-effort, forward-compatible approach is proposed instead — see `data-model.md`).
- A general file-manager. Image upload, if built, is a single authenticated endpoint.
- Changing the read-only Story overview's rendering pipeline for Scene `contents` (it stays
  Markdown via `Str::markdown()`).

## Library decision

The spec's favorite is **Redactix** for the HTML fields, with Tiptap and Milkdown as
alternatives. Recommendation:

- **Adopt Redactix for the HTML fields** *if and only if* two conditions hold (verify during
  the first task — see `open-questions.md`):
  1. Its image-upload handler can be pointed at **our own authenticated endpoint** (custom
     upload URL / handler callback), not a hardcoded third-party or unauthenticated one.
  2. It is actively maintained and its bundle size is acceptable.
- **Server-side HTML sanitization is required regardless of the library chosen.** The editor's
  output is untrusted user input. This is the single most important security decision in the
  feature and is independent of Redactix vs Tiptap vs Milkdown (see `security.md`).
- **Fallback:** if Redactix cannot be pointed at our own upload endpoint, or is unmaintained,
  use **Tiptap** (headless ProseMirror, mature, explicit upload handler). This is a bigger
  bundle but the security story is clearer.
- **Scene `contents` (Markdown-only):** simplest is to leave it as the current plain textarea
  (optionally with a lightweight Markdown helper). Adding Milkdown just for this one field
  contradicts the anti-bloat goal — flagged in `open-questions.md`.

## User stories

- As an author editing a Chapter description, I can make text bold, add a bulleted list, and
  insert a link using a `/` slash menu, and it saves as formatted content.
- As an author, when I reopen the form, the editor shows my content already formatted (not raw
  markup).
- As an author writing a Scene's prose (`contents`), I keep writing in Markdown as today.
- As a security-conscious owner, a malicious `<script>` pasted into a description never
  executes when the content is displayed.
- As an unauthenticated visitor, I cannot reach the image-upload endpoint (403/redirect), and
  I cannot upload into a project I don't own.

## Acceptance criteria

- Designated HTML fields render the WYSIWYG editor with working slash commands; content
  round-trips (save → reopen → same formatting).
- Scene `contents` remains Markdown-only and still renders correctly on the Story overview.
- All stored rich HTML is sanitized **server-side** before persistence; a `<script>`,
  `onerror=`, `javascript:` URL, etc. in the payload is stripped, proven by a feature test.
- Rich HTML is rendered with `{!! !!}` **only** through the shared display component that emits
  already-sanitized content; index/list previews do not break layout (tags stripped to a text
  excerpt).
- If image upload ships: the upload route requires `auth`; a non-owner posting to it gets
  `403`; uploads are validated (mime + size) via the existing `CodexMediaRules`; files land
  through `CodexMediaService`. Proven by feature tests including the negative case.
- Forms degrade gracefully: with JS disabled the underlying `<textarea>` still submits.
- `composer test` passes; new feature tests cover happy path, authorization, validation, and
  the sanitization invariant.
