---
status: draft
---

# Scene Edit Codex Attributes

Edit codex attribute values from the scene edit page.

## Problem

The scene edit page shows "Codex as of this scene", but it is read-only. To change an attribute value, the author leaves the scene and opens the codex entry. While writing a scene, this breaks the flow.

## Goals

- Edit an attribute value from the scene edit page, in the "Codex as of this scene" block.
- The edit applies at the scene's event. The value still belongs to the entry and the event, not to the scene.
- Reuse the existing attribute timeline rules and authorization.

## Non-goals

- No change to the data model.
- No new attributes or attribute definitions from the scene page.
- No edit of values at other events.
- No change to the codex entry edit page.

## Approach

- Follow the existing attribute timeline editing pattern on the codex entry edit page.
- Show an edit control per attribute row in the scene block.

## Open questions

- What happens when the entry has no value at the scene's event yet: add one, or edit the inherited one?
- Does a scene with no event hide the controls?
- Save with the scene form, or per row?
