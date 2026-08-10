# Duplicate entities — overview

## Problem

A writer who wants a near-copy of a scene or a codex entry has to recreate it by hand: retype the
fields, re-upload the images, re-add the aliases, re-pick the tags. Nothing in the app copies an
entity.

## Goals

* A "Duplicate" action on the index row and in the edit page's Actions card, for scenes and codex
  entries.
* A naming step between the click and the write, prefilled with a collision-free suggestion.
* A deep copy of the rows the entity *owns* (aliases, media, attribute values) and a shallow copy
  of what it *points at* (events, tags).

## In scope

| Entity | Owns (copied) | Points at (FK/pivot replicated) |
|---|---|---|
| Scene | — | `event_id`, `event_scene` |
| CodexEntry | `codex_aliases`, `codex_media` (rows **and files**), `codex_attribute_values` | `codex_entry_tag` |

## Non-goals

* **Acts and chapters.** They hold no prose of their own; duplicating one would produce an empty
  shell. Excluded deliberately, not deferred.
* **The manuscript tree.** Duplicating never walks *down* a hierarchy: no act copies its chapters,
  no chapter copies its scenes. "Children" in this feature means rows that belong to the entity
  itself — the codex entry's aliases and media — and the design must stay open to a future
  entity-owned table (a hypothetical `SceneExtraData`) joining that list.
* Events, plotlines, codex attribute definitions, projects.
* Copying revision history. A duplicate is new work; `RevisionRecorder` seeds its baseline lazily
  on the first autosave, so there is nothing to do.
* Cross-project duplication.
* Bulk duplication — no index in the app has multi-select.

## User stories

* As a writer I duplicate a scene from its index row, accept the suggested name "Arrival (2)", and
  land on the new scene's edit page, positioned right after the original.
* As a writer I duplicate a character entry and get its cover, reference images, aliases, timeline
  values and tags, without re-uploading anything.

## Acceptance criteria

* The copy is created in the same parent as the original (same chapter / same project and type).
* A duplicated scene lands at `original.position + 1`; every later sibling shifts down by one.
* The suggested name is `<name> (2)`, incrementing until free among same-type entities in the
  project.
* The submitted name is what gets saved — the writer may overwrite the suggestion entirely, and a
  collision is accepted.
* Media files are **copied**, never path-shared: deleting the original must not break the copy.
* `share_token` / `share_expires_at` are never copied.
* A non-owner gets a 403 on the duplicate route.

## Invariants this touches

* **Positions** (`HasSiblingPosition`): gaps are tolerated, uniqueness is not enforced. The insert
  shifts later siblings rather than renumbering the set.
* **Derived `scene_codex_entry` cache**: never copied — recomputed by `SceneReferenceMatcher`.
* **Media lifecycle** (`documentation/architecture.md`): every file has exactly one owning row. A
  shared path would make one deletion blank the other entity's images.
