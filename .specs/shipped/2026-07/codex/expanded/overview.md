# Codex — Overview

Expanded from [`.specs/codex/spec.md`](../spec.md). The **Codex** is a new top-level menu section (peer of *Timeline* and *Story*) that holds the story's reference entities — **Characters**, **Locations**, and **Organizations** — plus a project-wide system of **mutable, event-tied attributes** so a value (hair color, wall color, org size…) can change over the story's timeline and be resolved at any point in it.

## Problem statement

The app already models the *timeline* (plotlines, events) and the *manuscript* (acts → chapters → scenes). It has no place to describe the *things in the world* — who the characters are, where scenes happen, which factions exist — and no way to say "this fact was true only between these two events." Writers need a codex whose facts evolve with the plot and can be looked up from any scene (via the scene's "happens during" event).

## Goals

- A **Codex** nav dropdown with **Characters**, **Locations**, **Organizations** index pages, each an `x-table` list consistent with the existing Plotlines/Events/Scenes indexes.
- A shared entity foundation: every codex entry has a **name**, **description**, **aliases** (searchable), and **tags**.
- A **three-column edit page** (`col-8` main form / `col-2` tags & categories / `col-2` media) for every entry type.
- Per-type **attribute sheets** (character sheet vs location sheet vs organization sheet) built on one flexible attribute system.
- **Temporal attribute values**: a gap-free, event-anchored step function per (entry, attribute), resolvable "as of" any event — including the event a scene happens during.
- **Attribute administration**: create attribute definitions and choose which entity types they appear on.
- **Media**: one cover image, plus many reference images and reference files per entry, with validated uploads.

## Non-goals (v1)

- Rich attribute *types* beyond plain text values (enum/select, numeric, color pickers) — flagged in [`open-questions.md`](open-questions.md).
- Relationships *between* codex entries (character-in-organization, scene-in-location as first-class links). Only free-text/tags for now.
- Server-side search of attributes/aliases at scale; client-side + simple `LIKE` mirrors the existing `x-event-picker` tradeoff.
- Versioned media or media on the timeline (media is not event-tied).
- Full-text search across descriptions.

## User stories

1. As a writer I open **Codex → Characters**, add "Mélusine", give her aliases ("Mel", "the Serpent Lady") and tags ("protagonist", "fae").
2. On her sheet I set **Hair color = blonde** from the *Start* event, then insert **green** from the *Halloween* event and **black** from *Back to class* — with no gaps.
3. I open a scene that happens during *Back to class* and see Mélusine's hair resolved to **black** at that moment.
4. As a writer I create a new attribute **"Eye color"** and tick that it applies to **Characters** only; it now appears on every character sheet and nowhere else.
5. I upload a cover portrait plus three reference images and a PDF character brief to her entry.
6. A non-owner cannot view or edit any codex entry, attribute, or upload in my project (403).

## Acceptance criteria

- Codex nav appears only inside a project context (same `@if ($project = …)` guard as the existing nav), with three links.
- Each entry type has working index / create / edit / delete flowing authorization from the owning `Project` via `ProjectPolicy` (owner 2xx, non-owner 403).
- Aliases are matched by the index `search` box alongside name.
- For any (entry, attribute) there is **always** a value anchored at the project's *Start* event, and looking up the value at any datetime returns exactly one value (no holes, no overlaps) — deterministically, even when two anchor events share the same datetime (see the tie-break in [`attribute-timeline.md`](attribute-timeline.md)).
- Deleting a non-fixed event that anchors an attribute period removes that period and the previous period extends to cover it — still gap-free (see [`attribute-timeline.md`](attribute-timeline.md)).
- Attribute definitions restrict which entity types render them.
- Uploads are validated (mime + size) and stored on the `public` disk; deleting an entry removes its media rows and files.
- Every new endpoint ships a feature test covering happy path, 403, validation failure, and the gap-free invariant.

## Related conventions to honor

- **Authorization-via-project** — no per-entity policies; walk up to `project` (like `SceneController`).
- **Shallow nested routes** — `index/create/store` nested under the project, `edit/update/destroy` flat (see [`architecture.md`](architecture.md)).
- **Thin controllers** — validation in Form Requests, temporal/gap-free logic in a dedicated service (guidelines "Where logic lives").
- **`x-table` family + icon components** for all index pages.
- **No magic strings** — entity types and media collections are enums in `app/Enums`.
