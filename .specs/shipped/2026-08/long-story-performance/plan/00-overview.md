# Long Story Performance — plan overview

Paginate the Story overview one chapter per page (`chapter` mode, default), with
an opt-in whole-story view (`book` mode). Per-project setting. See
`../expanded/` for full design.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | render-mode-column | `StoryOverviewMode` enum + `overview_render_mode` column on `projects` |
| 02 | extract-chapter-partial | One shared per-chapter render partial; `book` output byte-for-byte unchanged |
| 03 | chapter-mode-render | Controller branch: render one chapter with whole-book context (numbering, TOC, totals) |
| 04 | chapter-pager-and-toc | Prev/next chapter navigation + TOC links carry `?chapter={id}` |
| 05 | mode-switch | Owner-only header control + PATCH endpoint to persist the mode |

Strict order: each depends on all lower-numbered tasks being in
`plan/implemented/`.

## Binding decisions (settled in expansion + grill — do not re-litigate)

- **Default mode is `chapter`.** No size-based auto default. Existing project 4
  paginates like everything else — no special-casing.
- **Chapter addressed by id** in `?chapter={id}` (stable across reorder; drives
  the auth walk). Never by project-wide number.
- **Setting is a column on `projects`**, not a new `StorySetting` model — a
  single enum view-preference is a project attribute.
- **Markdown-render caching is out of scope.** `chapter` mode makes the overview
  fast without it. `book` mode on a huge story stays as slow as today, by
  design. A follow-up spec (`scene-render-cache`) owns caching + EPUB.
- **Mode switch lives in the overview header, owner-only.** Rendered only when
  the user can `update` the project. Not on the project-edit page.
- **Act header always shows** on a `chapter`-mode page (the current chapter's
  act), so a mid-act chapter page keeps the reader's place.
- **TOC and word totals are whole-book in both modes.**

## Invariants every task must preserve

- **Authorization via the owning project.** The overview `authorize('view',
  $project)`; the mode PATCH `authorize('update', $project)` mirrored in its Form
  Request. A `?chapter={id}` whose chapter belongs to another project is a 403,
  never a 200 — walk `chapter->act->project` and compare to the route `{project}`.
- **`position` ordering untouched.** Scene reorder stays AJAX
  (`scene-reorder.js`), identical within one chapter. No task changes reorder.
- **No N+1 / no whole-story `contents` load in `chapter` mode.** Only the current
  chapter's scene bodies are fetched; numbering, TOC and totals use
  contents-free queries.
- **`book` mode is behaviour-preserving.** Task 02 must not change its rendered
  output; later tasks must not regress it.
