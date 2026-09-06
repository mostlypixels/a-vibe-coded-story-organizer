---
status: draft
---

# Codex entry from the scene editor

A name appears while she is writing. Recording it means leaving the paragraph for a form
that opens with fifteen empty attribute boxes, so she does not record it. The codex ends
up in a text file next to the app.

The scene editor already detects codex references in the prose and lists them in a
sidebar. A name it cannot match is exactly the moment to offer creating the entry.

## Goals

- Create a codex entry from the scene editor: a name, a type, done. Back to the paragraph.
- The new entry appears in the scene's reference sidebar without a reload.
- Creating an entry never saves the scene, and never loses unsaved prose.
- Reachable from a selection in the editor, so the name she just typed becomes the entry
  name with no retyping.

## Non-goals

- No description, aliases, tags or attributes in this flow. One name and one type. The
  full form stays where it is.
- No change to `SceneReferenceMatcher`. Detection already works; this is about the entry
  not existing yet.
- No suggestion engine that proposes names from the prose. That is a separate feature and
  a harder one.
- No new entry types.

## Approach

- One JSON endpoint that creates an entry from a name and a type, going through
  `CodexEntrySaver` so the duplicate-name and alias rules stay in one place.
- Alpine posts it from the scene editor with the form's CSRF token, then adds the entry to
  the sidebar list.
- References are recomputed on save by `SceneReferenceMatcher::syncScene()`. The sidebar
  entry added here is optimistic until then — decide whether to resync immediately or to
  mark it as pending.
- The same shape as [`ajax-inline-events`](../ajax-inline-events/spec.md), which does this
  for events. Build the two on one pattern, or build that one first.

## Open ends

- Whether a duplicate name blocks, warns, or silently links to the existing entry. The
  edit form warns today.
- Whether an entry created this way and then abandoned is a problem. Events accept it; a
  codex entry with a name and nothing else may be worse than none.
- Whether the trigger is a toolbar button, a selection popover, or a control on the
  reference sidebar.
