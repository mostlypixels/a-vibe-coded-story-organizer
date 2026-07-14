---
status: draft
---

# Codex ↔ scene references: capped lists with a "see more" page

## Problem

`alias_references_v1` (shipped) added the derived `scene_codex_entry` pivot and two read-only
cards that render it in full, with no cap:

- **Codex entry edit page** (`resources/views/codex/edit.blade.php`) — "Referenced in scenes"
  card, fed by `CodexEntryController::referencingScenesInTimelineOrder()`, ordered by
  `(event_datetime, id)`.
- **Scene edit page** (`resources/views/scenes/edit.blade.php`) — "Codex references" card, fed
  by `$scene->codexReferences` (ordered `(type, name)`).

A well-referenced entry (e.g. a protagonist mentioned in most scenes of a project) or a scene
that mentions many codex entries produces a long inline list on what is otherwise a compact
edit-page sidebar/section, pushing the rest of the page down.

## Goals

- Cap each card to a small number of inline rows (exact number TBD during expansion — look at
  what similar capped lists in this app already use, if any, for consistency; otherwise pick a
  reasonable default like 5–10).
- When the full set exceeds the cap, show a "See more" link/button leading to a dedicated
  full-list page (matching this app's existing preference for real pages over AJAX
  expand-in-place — see e.g. the Story overview, Codex index).
- Apply this in **both directions**: codex entry → referencing scenes, and scene → referenced
  codex entries. The two lists have different orderings (timeline order vs. `(type, name)`) —
  preserve each on both the capped card and its full-list page.
- The full-list page needs its own route, controller action, authorization (walk to the owning
  `Project` via `ProjectPolicy`, same as the rest of this app), and a simple view — likely
  reusing the same list markup as the capped card's `<ul>`, or extracting a shared partial.

## Non-goals

- No change to the matching logic (`App\Services\SceneReferenceMatcher`) or to when the pivot
  is recomputed.
- No pagination controls (page 2, 3, ...) on the full-list page unless the expansion step
  decides the counts genuinely warrant it — a single "everything" page is likely sufficient
  given realistic project sizes.
- No change to the "Resync codex references" command/button or the manual-resync flow.

## Rough approach

- Add a `GET` route + controller action per direction (or one action taking a "direction"
  parameter) that authorizes via the owning project and renders the un-capped list, reusing the
  existing ordering logic already in `CodexEntryController` (timeline order) and
  `SceneController`/`Scene::codexReferences` (`(type, name)` order).
- In each edit-page card, slice the collection to the cap and conditionally render the "See
  more (N total)" link only when the full count exceeds the cap.
- Consider extracting the repeated `<li>` markup (scene row: name + event/"No event assigned";
  entry row: name + type label) into a shared Blade partial/component used by both the capped
  card and the full-list page, to avoid drifting between the two.
