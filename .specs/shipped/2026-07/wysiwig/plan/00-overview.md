# WYSIWYG textareas — Implementation plan (overview)

This is the manual for the `plan-implementer` agent. It is **never implemented or moved**.
Read the matching `.specs/wysiwig/*.md` design docs for detail; this file states the order,
the binding decisions, and the invariants every task must preserve.

## Execution order

| # | Task | Purpose (one line) |
| --- | --- | --- |
| 01 | `01-sanitization-foundation.md` | Add HTMLPurifier + the `RichTextFields` config, `HtmlSanitizer` service, and `SanitizeHtml` rule. Pure backend, unit-tested. No wiring yet. |
| 02 | `02-sanitize-on-write.md` | Apply sanitization on every write path (per-field set-mutators) + Form Request rules across the rich-HTML fields; reseed. Feature-tested. |
| 03 | `03-safe-rendering.md` | `x-rich-text` display component + excerpt; swap read/index/show views to render sanitized HTML. HTTP-tested. |
| 04 | `04-editor-ui.md` | Spike + adopt the editor library, build the `x-wysiwyg` component, swap the rich-HTML textareas in every form. Verified by build + manual round-trip. |
| 05 | `05-docs.md` | `documentation/rich-text.md`, CLAUDE.md/architecture note, CHANGELOG. Verified by inspection. |

Tasks 01→02→03 each leave the app working with an incrementally richer pipeline (even before
the WYSIWYG editor exists in task 04, content typed into the plain textareas is sanitized and
rendered as HTML). 04 adds the authoring affordance; 05 documents it.

## Binding decisions (do not re-litigate in later tasks)

- **No image upload in v1.** Inline images, an upload endpoint, and a `project_media` table are
  **deferred to v2**. The slash menu omits image insert. Do not add an upload route or media
  table. (Resolves open question 6.)
- **Editor library: spike then adopt.** Task 04 verifies Redactix is maintained and accepts a
  custom config; adopt it, else fall back to **Tiptap**. All other tasks are
  **library-agnostic** — the editor is only touched behind the `x-wysiwyg` component and the
  `resources/js/wysiwyg.js` module. (Resolves open question 1.)
- **Sanitization = HTMLPurifier, applied via per-field set-mutators** (not a `booted()` hook,
  not controller-only). Mutators run on every Eloquent write including under
  `WithoutModelEvents`, so the DB never holds unsafe HTML regardless of entry path. (Resolves
  open questions 2, 3.)
- **Scene `notes` becomes rich HTML.** Scene **`contents` stays Markdown-only** — unchanged
  `ValidMarkdown` validation, unchanged `Str::markdown()` rendering on the Story overview.
  (Resolves open question 4/5.)
- **No data backfill.** The app is pre-production; reseed `MelusineSeeder` with clean HTML
  rather than migrating existing rows. (Resolves open question 7.)
- **Index/list previews use a `stripTags`+`limit` text excerpt**, never full HTML in a table
  cell. Full rich rendering only on detail/show pages. (Resolves open question 10.)
- **Field taxonomy has one source of truth**: `App\Support\RichTextFields` (which fields are
  rich HTML). No hardcoded field lists scattered across views/requests.

## Rich-HTML fields (the exact scope)

`Project.description`, `Act.description`, `Chapter.description`, `Plotline.description`,
`Event.description`, `Scene.description`, `Scene.notes`, `CodexEntry.description`.

**Not rich HTML:** `Scene.contents` (Markdown-only, untouched).

## Invariants every task must preserve

1. **Never trust client output — sanitize server-side on write.** Every rich-HTML field passes
   through `HtmlSanitizer` before persistence. A rich field must never be stored unsanitized.
2. **Render rich HTML with `{!! !!}` ONLY through `x-rich-text`, on already-sanitized data.**
   No other `{!! !!}` on user content anywhere. Index cells use the escaped text excerpt.
3. **Slash-menu output ⊆ sanitizer allow-list.** The two lists are kept in sync; anything the
   editor can produce must survive sanitization, and nothing outside the allow-list is stored.
4. **Authorization via `ProjectPolicy` walk-up**, mirrored in Form Requests; every touched
   write action keeps its owner-succeeds / non-owner-403 coverage (fill gaps for Act/Chapter/
   Plotline/Event where no feature tests exist yet).
5. **Scene `contents` stays Markdown-only** — do not route it through the sanitizer or the
   editor; keep `ValidMarkdown` and the Story-overview `Str::markdown()` rendering intact.
6. **Progressive enhancement** — forms keep a real `<textarea>` so a JS-off submit still works
   and `old()` repopulates on validation failure.
7. **Thin controllers.** New logic lives in `app/Support` (config), `app/Services`
   (`HtmlSanitizer`), `app/Rules` (`SanitizeHtml`), and Blade components — not in controllers.
8. **Shallow routing / no new policies** — this feature adds no new resource routes and no new
   policy (image upload, which would have, is deferred).

## Open questions left unresolved (non-blocking)

- Q8/Q9 (image storage table, orphaned uploads) are **moot in v1** — image upload is deferred.
- Q11 (docs placement) is decided by task 05 (`documentation/rich-text.md`).
