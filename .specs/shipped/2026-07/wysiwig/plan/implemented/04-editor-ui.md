# Task 04 — Editor UI (library spike/adopt, x-wysiwyg, form swaps)

## Scope

Add the Notion-like WYSIWYG authoring experience on top of the already-safe pipeline. This is
the frontend deliverable; the backend contract (what gets stored/rendered) is unchanged.

**Builds:**
- **Library spike, then adopt (at the top of this task):** verify **Redactix** is actively
  maintained and works as a bundled editor we control (it accepts custom config; we do **not**
  wire any upload — image upload is deferred, so the slash menu omits image insert). If Redactix
  is unmaintained or unsuitable, fall back to **Tiptap**. Record the choice in the commit.
- Add the chosen editor as an npm dependency; configure it in `resources/js/wysiwyg.js`,
  imported from `resources/js/app.js` (which already boots Alpine). One editor library only
  (anti-bloat). Editor CSS via `resources/css/app.css` if needed; keep it from fighting the
  Tailwind `prose` output.
- **Slash commands:** headings, bold/italic/underline/strike, bulleted + numbered lists,
  blockquote, inline code + code block, link, horizontal rule. **No image insert** (deferred).
  Everything the menu produces must be within the task-01 allow-list — reconcile the two lists.
- `resources/views/components/wysiwyg.blade.php` (`x-wysiwyg`) — props `name`, `id`, `value`,
  `rows`/min-height, `placeholder`, `disabled`. **Progressive enhancement:** renders a real
  `<textarea>` holding the value; Alpine mounts the editor over it, hydrates from it, and syncs
  edits back into the textarea before submit. Hide pre-mount state with `style="display:none"`
  (no `x-cloak`), matching the other interactive components.
- **Swap the rich-HTML textareas → `<x-wysiwyg>`** in: `projects/{create,edit}`,
  `acts/{create,edit}`, `chapters/{create,edit}`, `plotlines/{create,edit}`,
  `events/{create,edit}`, `scenes/{create,edit}` (`description` **and** `notes`),
  `codex/partials/fields` (`description`; drop the "(Markdown)" label + `font-mono`).
  **Leave `scenes` `contents`** as the plain Markdown textarea (keep its label + `font-mono`).

**Does NOT:** add any upload endpoint, `project_media` table, or inline images (v2). Does not
change validation, mutators, or rendering (tasks 01–03 own those).

## Depends on

- **02** (writes are sanitized) and **03** (rendering) — so any content the editor produces is
  stored safely and displays correctly. Implement after both.

## Key decisions already made (binding)

- **Spike then adopt**; library-agnostic behind `x-wysiwyg` + `resources/js/wysiwyg.js`.
- **No image upload / no image slash command** in v1.
- **Progressive enhancement** over a real `<textarea>`; `old()` must repopulate on validation
  failure via the textarea value.
- `Scene.contents` stays a Markdown textarea — not the editor.

## Docs to consult

`ui.md` (component shape, view list, a11y), `architecture.md` §4/§6 (component + build wiring),
`overview.md` (library decision + fallback), `security.md` §3 (supply-chain/bloat).

## Verification

This task is frontend; verify beyond PHPUnit:
- `npm run build` succeeds (editor bundles cleanly through Vite).
- Feature test (cheap regression): each swapped create/edit route returns 200 and the rendered
  form still contains a submittable `name="description"` / `name="notes"` control (the
  progressive-enhancement `<textarea>`), so no-JS submit is intact.
- `/verify` (manual/browser): open an edit form, use a slash command (e.g. bullet list + bold),
  save, reopen → formatting preserved; the show page renders it; disabling JS still submits the
  textarea. Confirm the Scene `contents` field is still the Markdown textarea.
- `composer test` and `vendor/bin/pint` green.
