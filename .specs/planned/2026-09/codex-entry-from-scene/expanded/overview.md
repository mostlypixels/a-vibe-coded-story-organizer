# Overview

## Problem

Recording a name costs a page load, a fifteen-box form, and the way back. So the name is
not recorded.

`codex-attributes-on-demand` (expanded, 2026-09) removes the fifteen boxes. This spec
removes the page load. They are the same wound; ship them in either order, but do not
design them apart.

## What the code already gives us

- `SceneReferenceMatcher::syncScene()` recomputes one scene's pivot. Cheap.
- Scene contents autosave through `FieldAutosaveController`, which already accepts
  `run_matcher` and calls `syncScene()` on a coarse trigger. So the server usually holds
  current prose without a form save.
- `resources/js/wysiwyg.js` has a slash menu (`buildSlashItems`) and already talks to the
  page with a `wysiwyg:text-changed` CustomEvent. Both are the seam this feature needs.
- `CodexEntrySaver::create()` holds the alias and reference rules.

## The cost nobody has priced

`CodexEntrySaver::create()` ends with `SceneReferenceMatcher::syncProject()` — a
**project-wide** rescan of every scene. See `alias_references_asynchronous` for the
measured cost. Running that mid-paragraph, for a 400-chapter serial, defeats the whole
feature.

Decision: the quick endpoint skips the project rescan and syncs the current scene only.
The new name therefore does not match *other* scenes until each next saves, or until the
existing resync command runs. That is accepted debt, not an oversight — record it in
`standing-issues.md` when this ships.

## Goals

- Name + type + Create, from the scene editor, without leaving it.
- Prefill the name from the editor selection.
- The sidebar gains the entry with no reload.
- The scene is never saved, and unsaved prose is never lost.

## Non-goals

Description, aliases, tags, attributes, new entry types, name suggestion from prose,
changes to `SceneReferenceMatcher`.

## Acceptance criteria

- Creating from the editor leaves the scene's `updated_at` and prose untouched.
- The created entry has a name and a type, and no attribute value rows (which
  `codex-attributes-on-demand` makes true; today `seedAttributeBaselines()` would write
  fifteen blanks — the two features collide here, see `open-questions.md`).
- A name matching the prose appears in the sidebar within one round-trip.
- An exact duplicate name of the same type is refused, with a link to the existing entry.
- A non-owner gets 403.
