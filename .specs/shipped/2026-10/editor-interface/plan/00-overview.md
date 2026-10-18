---
title: Editor Interface — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-codex-alias-search.md` | Codex candidates match aliases. Serves every picker. |
| 02 | `02-layout-preference.md` | `users.editor_layout`, `EditorLayout`, `config/editor.php`, PATCH endpoint. |
| 03 | `03-editor-cards-table.md` | `editor_cards` table, model, PUT state endpoint. |
| 04 | `04-card-content.md` | `CodexAsOfResolver::resolveEntry()`, codex and note card endpoints and partials. |
| 05 | `05-editor-page-core.md` | Routes, `EditorController`, "Write" nav link, full-height grid, prose editor, sticky toolbar, empty state. Depends on 02. |
| 06 | `06-header-extras.md` | Previous/next, today's progress, scroll and cursor restore. Depends on 05. |
| 07 | `07-left-panel.md` | Recent scenes and scene search. Depends on 05. |
| 08 | `08-right-panel.md` | Codex and Notes sections, search, cards, `cards.js`. Depends on 01, 03, 04, 05. |
| 09 | `09-panel-resize-collapse.md` | `layout.js`: resize, collapse, section collapse, save, small-screen drawers. Depends on 05, 08. |
| 10 | `10-docs-changelog.md` | `documentation/features/editor.md`, index links, CHANGELOG, manual check. Depends on all. |

01 to 04 are independent. The "Write" link appears in 05, so a user sees a working, plain editor from then on. Each later task adds to it.

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`. Where `expanded/*.md` differs, these win.

1. **Each right section keeps its own cards.** Codex picks open in Codex, note picks in Notes.
2. **Codex search finds entries, not attributes.** A codex card shows attributes as of the scene's "happens during" event.
3. **Codex search matches names and aliases.** The result label stays the entry name.
4. **Scene switch is a full page load.** No editor swap by fetch.
5. **Prose only.** Title, status, chapter, event and description stay on `scenes.edit`. The header links there.
6. **Recent scenes: whole project, 15 rows,** newest `updated_at` first, book in the context line.
7. **Below `lg`:** both panels start collapsed; an open panel is an overlay drawer; stored widths do not apply.
8. **Cards are saved per user and per scene on the account** (`editor_cards`). This replaces the `sessionStorage` card list in `expanded/architecture.md`.
9. **The scene's linked notes (`$scene->notes`) load as collapsed cards** with no row needed. A `closed` row hides one; search reopens it.
10. **Cards are read-only,** with a link to the full page in a new tab.
11. **The logo stays the Dashboard link.** "Write" is its own link after Dashboard.
12. **Full width and full height under the menus.** The page body never scrolls; each of the three columns scrolls alone.
13. **The editor toolbar is sticky** at the top of the centre column. Opt-in prop on `x-wysiwyg`, so other pages do not change.
14. **Previous/next scene links** in the header, in story order inside the scene's book. Hidden at the ends.
15. **Today's progress** in the header: same figure and goal as the dashboard "Today" bar. It updates live from the autosave response.
16. **Scroll position and cursor are kept per scene in `localStorage`.** Per-browser by design.
17. **A codex entry not present at the scene's moment still shows its card,** with "Not present at this scene's moment", its description, and no attribute values.
18. **Out of scope for v1:** quick note creation, auto-opening mentioned codex entries, editing inside cards, panel keyboard shortcuts.

## Core invariants every task preserves

- **Authorization through the owning project.** Scene routes: `update` on `$scene->book()->project`. Card and search reads: `view` on the target's project. Each endpoint has a non-owner 403 test.
- **No cross-project ids.** Any id from a request (scene, codex entry, note) resolves through `LinkTargetType::queryFor($project)` or `$project->sceneQuery()`. A foreign id is 404.
- **Preferences write only `$request->user()`.** No policy, like `PageSizeController`.
- **One autosave field per page:** `scene.contents` through `x-autosave-field`. Its tab lock, conflict and revision behaviour does not change.
- **Keep the Tiptap `Editor` out of Alpine reactive data** (`documentation/features/rich-text.md`).
- **Browser storage is a convenience only.** Wrap every access in try/catch; the page works without it.
- **Rich content renders through `x-rich-text` with its project,** so `entity:` links resolve.
- **Manuscript `position` and the main plotline are not touched.**
