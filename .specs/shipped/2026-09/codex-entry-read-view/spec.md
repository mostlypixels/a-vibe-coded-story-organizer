---
status: shipped
shipped: 2026-09-05
planned: 2026-09-05
expanded: 2026-09-05
---

# Codex Entry Read View

Clicking a codex entry opens the edit form. There is no way to read one. A writer who
opens an entry to check a fact lands in a live form with a cursor in the name field,
five screens tall, with the answer buried among empty attribute boxes.

Only `Project`, `Book` and the public shared scene have a `show` route today. Scenes,
events and plotlines have the same gap, but the codex is where facts get looked up, so
it goes first.

## Goals

- A read-only page for a codex entry. Edit is a button on it.
- The list pages, the search results and the scene sidebar link to the read page.
- Show what a reader wants: name, aliases, tags, description, the attribute values that
  are set, the lifespan, and the scenes that reference the entry.
- Leave out what only an editor needs: empty attributes, timeline period controls,
  duplicate-name warnings.
- Move the shared read logic out of `CodexEntryController` so `show` and `edit` build it
  the same way. The controller already assembles referencing scenes and attribute
  timeline sheets inline.

## Non-goals

- No change to the edit form's contents. Trimming empty attributes there is its own feature.
- No read page for scenes, events or plotlines yet. Prove the shape on the codex first.
- No public or shareable link. This is the same logged-in, project-owner page as the rest.
- No new history or revision surface. Link to the existing History page.

## Approach

- Add `show` to the `projects.codex-entries` resource route and a `show` action that
  authorizes through the owning project, the same as `edit`.
- Extract the read-side assembly from `CodexEntryController::edit()` into a small domain
  object under `app/Services`, and call it from both actions. Candidates: the referencing
  scenes in timeline order, and the attribute timeline sheets.
- Reuse the existing partials where they are already read-only. `as-of.blade.php` and
  `attribute-timeline.blade.php` look close; `fields.blade.php` is the form and is not.
- Point the entry links on the codex list pages, the search results and the scene
  reference sidebar at `show` instead of `edit`.

## Open ends

- Whether the attribute timeline belongs on the read page in full, or collapsed to the
  current value with the history behind a link.
