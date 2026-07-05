# WYSIWYG textareas — Open questions

Decisions to confirm before/while implementing. Grouped by how blocking they are.

## Blocking — resolve first (task 1 is a spike)

1. **Redactix viability.** Confirm two things by reading the library:
   (a) it exposes a **custom image-upload handler / URL** so we can point it at our own
   authenticated endpoint (the spec's explicit requirement), and (b) it is actively
   maintained with an acceptable bundle size. If either fails → fall back to **Tiptap**. This
   gates the whole frontend approach.

2. **HTMLPurifier dependency.** Server-side sanitization is mandatory and best done with
   HTMLPurifier (`mews/purifier` Laravel wrapper, or `ezyang/htmlpurifier` directly). This adds
   a Composer dependency — acceptable given the security requirement, but confirm it's allowed
   given the "avoid bloat" constraint. (A hand-rolled sanitizer is **not** recommended.)

3. **Where sanitization runs:** model set-mutator per rich field (robust, covers
   seeder/tinker) vs Form Request pass (closer to existing `ValidMarkdown` pattern, but
   bypassed by non-HTTP writes). Recommend the **set-mutator**. Confirm.

## Field scope

4. **Does `Scene.notes` become rich HTML or stay Markdown?** The spec only pins `contents` as
   Markdown-only. `notes` is currently Markdown. Recommend making it rich HTML for consistency
   with descriptions, but it's a judgment call — confirm.

5. **Does `Scene.contents` get a Markdown editor, or stay a plain textarea?** Spec says
   Markdown-only, not "no editor." Adding Milkdown just for this one field contradicts the
   anti-bloat goal. Recommend **keep the plain textarea** (optionally a light Markdown toolbar
   later). Confirm.

6. **Is inline image insertion in scope at all?** Galleries are explicitly deprioritized. A
   single inline `<img>` via the authenticated upload endpoint is the minimum "image" feature.
   If images are fully out of scope for v1, the upload endpoint / `project_media` table /
   upload tests all drop out — significantly smaller feature. **Confirm whether v1 includes
   any image upload.**

## Data

7. **Backfill existing content?** Descriptions are plain text (newlines will collapse as
   HTML); codex description / scene notes are Markdown (source would show literally). Given the
   app is pre-production, recommend **no backfill, just reseed** `MelusineSeeder`. Confirm no
   real authored data needs migrating.

## Storage (only if image upload ships — see Q6)

8. **Where do editor images live?** Recommend a new lightweight **`project_media`** table
   scoped to `project_id` (mirrors `codex_media`), served off the public disk like Codex
   media, and purged in `Project::deleting` alongside `purgeProject()`. Alternatives: reuse
   `codex_media` with a nullable owner, or DB-less path storage. Confirm the table.

9. **Orphaned uploads** (image uploaded, then its paragraph deleted) are left on disk until the
   project is deleted. Acceptable for v1 (matches the deprioritized-gallery scope)? Recommend
   yes, document it, no GC now.

## Conventions

10. **Index preview rendering:** confirm the `stripTags`+`limit` excerpt approach for the
    `x-table` list cells (vs rendering full HTML, which would break table layout). Recommended.

11. **New `documentation/` page.** The guidelines require documenting *why*. Plan a
    `documentation/rich-text.md` (or a section in `architecture.md`) covering the sanitizer
    allow-list, the "render only via `x-rich-text`" rule, and the upload authorization — plus a
    CHANGELOG `[Unreleased]` entry. Confirm placement.
