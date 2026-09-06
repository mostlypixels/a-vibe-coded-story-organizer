---
status: shipped
shipped: 2026-09-06
planned: 2026-09-06
expanded: 2026-09-06
---

# Entity Read Views

The codex entry got a read page. Every other entity still opens its edit form when you
click it. A writer who wants to see what is in a scene, an act or a plotline lands in a
live autosaving form, with the content mixed among controls that change it.

Plotlines, acts, chapters, scenes and events have no `show` route. Only `Project`, `Book`
and the public shared scene do.

## Goals

- A read-only page for each of the five: plotline, act, chapter, scene, event.
- Edit is a button on the read page, not the destination of every link.
- Each page shows what a reader wants and what the entity belongs to:
  - Plotline: description, colour, the scenes on it, in order.
  - Act: description, its chapters and scenes, in order.
  - Chapter: description, its scenes, in order, and its act.
  - Scene: title, prose, summary, point of view, status, word count, the codex entries
    it references, its chapter and act.
  - Event: date, description, what it starts or ends in the codex, the scenes on it.
- Index pages, search results and every cross-link point at the read page.
- Every list row gets a "view" button with an eye icon, next to edit and delete. The
  `x-icon-view-link` component already exists.
- Follow the shape proved by `codex.show`: same layout, same header with the edit button,
  same read-only partials.

## Non-goals

- No change to any edit form.
- No public or shareable links. The shared scene page stays as it is.
- No new content. Each page shows what the model already holds.
- No new revision or history surface. Link to the existing History page.
- No reader or export mode for a whole book. One entity per page.

## Approach

- Add `show` to each of the five shallow resource routes, authorized through the owning
  project, the same as `codex.show`.
- Move read-side assembly out of the `edit` actions into small services under
  `app/Services`, as `ReferencingScenes` and `CodexAttributeSheets` already do, and call
  them from both actions.
- Reuse the read-only partials that exist. Add shared components only where a second page
  needs the same block; do not build a generic "entity show" abstraction.
- Repoint the links: the six list pages, search results, the story overview, the timeline
  and the scene sidebar.

## Open ends

- Whether a scene read page shows the full prose or a summary with the prose behind the
  editor. The prose is the scene; a page that hides it is not a read page.
- Whether an act or chapter page repeats its scenes' summaries, or only lists titles.
- Whether the story overview should keep linking to the edit form, since dragging and
  reordering is what it is for.
