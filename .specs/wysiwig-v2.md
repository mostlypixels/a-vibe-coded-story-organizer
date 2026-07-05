---
status: draft
---

# Wysiwig textareas — v2

v1 (`.specs/wysiwig.md`, shipped 2026-07-05) delivered the Notion-like WYSIWYG editor and the
mandatory server-side sanitization pipeline, but deliberately deferred two things and left one
loose end. v2 closes them. It builds **on top of** the v1 pipeline — the sanitization allow-list
(`App\Support\RichTextFields`), `HtmlSanitizer`, per-field set-mutators, and the
render-only-via-`x-rich-text` rule are unchanged foundations, not up for redesign.

The same binding constraint from v1 still holds: **avoid bloat of the codebase / libraries, to
avoid security issues**, and **never trust client editor output** — everything new must still
pass through the server-side sanitizer, and any new allowed tag/attribute (e.g. `img`) must be
added to the `RichTextFields` allow-list explicitly.

The shipped editor is **Tiptap StarterKit** behind the `x-wysiwyg` component
(`resources/js/wysiwyg.js`, progressive enhancement over a real `<textarea>`). v2 stays on
Tiptap — no library swap.

## 1. Slash command menu

v1 shipped an always-visible **toolbar** instead of the `/`-triggered slash menu the original
spec implied, because a slash menu needs a popup-positioning solution that wasn't installed.

v2 adds the **Notion-like `/` slash command menu**. `@tiptap/suggestion` is **already a project
dependency** (installed in v1, currently unused) — v2 activates it. The one open question is the
popup/positioning approach: prefer a **minimal, self-hosted positioner** (a small Alpine/CSS
anchored popup, or `@floating-ui/dom` if a dependency is truly warranted) over a heavier bundle
like `tippy.js` — decide during the spike, honoring the anti-bloat constraint.

- Slash commands must cover the same feature set as the current toolbar: headings, bold /
  italic / underline / strike, bulleted + numbered lists, blockquote, inline code + code block,
  link, horizontal rule — **plus image insert** (see §2, only if §2 ships).
- Decide whether the toolbar stays, is removed, or becomes a lighter bubble/selection toolbar
  once the slash menu exists. Recommend keeping a minimal toolbar for discoverability; confirm.
- Everything the slash menu can produce must stay **⊆ the `RichTextFields` allow-list**, same
  reconciliation rule as v1.
- Keyboard operable and accessible.

## 2. Image upload

This is the big v1 deferral. The original spec's hard security requirement stands: **image
upload must not allow non-logged-in users to upload**, and uploads must be scoped/authorized to
a project the user owns.

- New **authenticated upload endpoint**; authorization walks up to `Project` via `ProjectPolicy`
  (same pattern as every other child resource — no new policy). Reject non-owners and
  unauthenticated requests.
- New lightweight **`project_media`** table scoped to `project_id`, mirroring `codex_media`,
  served off the public disk like Codex media. Purged in `Project::deleting` alongside the
  existing `purgeProject()` cleanup (files must be removed before/around the FK cascade, same
  lesson as Codex media).
- Editor: wire Tiptap's image node + the slash menu's "image" command to the upload endpoint.
  Add `img` (with a constrained `src` — same-origin/relative or the allowed schemes only) to the
  `RichTextFields` allow-list, and confirm the sanitizer keeps `img` attributes safe (`src`,
  `alt`; strip everything else). Image **galleries remain out of scope** (deprioritized in v1).
- **Orphaned uploads** (image inserted, then its paragraph deleted) — v1 documented that GC was
  N/A because there were no uploads. v2 should decide: leave orphans until project deletion
  (simplest, matches Codex media), or add a GC pass. Recommend documenting the simple approach
  unless there's a reason to reclaim disk sooner.
- Validation: file type allow-list (jpeg/png/webp/gif), size cap, and store outside the webroot
  or via the public disk symlink like Codex.

## 3. Housekeeping

- If, after the spike, the slash menu does **not** use `@tiptap/suggestion` after all, prune it
  so it isn't a lingering unused dependency. (If §1 uses it, this is moot.)
- Update `documentation/rich-text.md`: remove the "image upload deferred to v2 / GC N/A" notes,
  document the upload endpoint + authorization + `project_media` + allow-list `img` addition,
  and document the slash menu. CHANGELOG `[Unreleased]` entry.

## Out of scope (still)

- Image galleries / multi-image blocks.
- Any change to the v1 sanitization architecture, the `x-rich-text` render rule, or the
  `Scene.contents` Markdown-only carve-out (still Markdown, still no WYSIWYG).
